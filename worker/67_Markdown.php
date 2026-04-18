<?php

/*
 *
 *
 *  Markdown
 *
 *
 */



define( 'WORKER_MARKDOWN_RESULT_NO_WORK',          0 );
define( 'WORKER_MARKDOWN_RESULT_BATCH_PROCESSED',  1 );
define( 'WORKER_MARKDOWN_RESULT_COMPLETED',        2 );



/*
 *
 *  Build a safe comma-separated SQL list of pilot markdown brand ids
 *
 */

function WorkerMarkdownBrandIdsSql()
{
    $brand_ids = [];

    foreach( MD_BRAND_IDS as $brand_id )
    {
        $brand_id = intval( $brand_id );
        if( $brand_id > 0 )
        {
            $brand_ids[ (string)$brand_id ] = $brand_id;
        }
    }

    if( count( $brand_ids ) === 0 )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    return implode( ',', $brand_ids );
}



/*
 *
 *  Check whether there is a markdown document already in progress
 *
 */

function WorkerMarkdownHasDocumentInProgress()
{
    $brand_ids = WorkerMarkdownBrandIdsSql();

    if( $brand_ids === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document = DbDocumentMarkdownInProgress( $brand_ids );

    return $document !== false;
}



/*
 *
 *  Check whether markdown generation must be initialized from scratch for the current PDF
 *
 */

function WorkerMarkdownNeedsInitialization( $document )
{
    return $document['lock'] !== WORKER_MARKDOWN_LOCK;
}



/*
 *
 *  Compute first page of the next markdown batch
 *
 */

function WorkerMarkdownBatchStart( $document )
{
    return $document['md_page_index'] + 1;
}



/*
 *
 *  Compute last page of the next markdown batch
 *
 */

function WorkerMarkdownBatchEnd( $document )
{
    $start = WorkerMarkdownBatchStart( $document );
    $end = $start + WORKER_MARKDOWN_PAGE_BATCH - 1;

    if( $end >= $document['pages_count'] )
    {
        $end = $document['pages_count'] - 1;
    }

    return $end;
}



/*
 *
 *  Return current worker memory usage in megabytes
 *
 */

function WorkerMarkdownMemoryUsedMegabytes()
{
    return intdiv( memory_get_usage( true ), 1000 * 1000 );
}



/*
 *
 *  Build the single-line terminal progress message for one markdown batch
 *
 */

function WorkerMarkdownTerminalProgressMessage( $document_id, $batch_start, $batch_end, $pages_count )
{
    $memory_used = WorkerMarkdownMemoryUsedMegabytes();

    return 'Producing markdown via Docling - Mem ' . $memory_used . ' MB - Block ' . ($batch_start + 1) . '-' . ($batch_end + 1) . '/' . $pages_count;
}



/*
 *
 *  Write single-line markdown batch progress on terminal
 *
 */

function WorkerMarkdownLogTerminalProgress( $document_id, $batch_start, $batch_end, $pages_count )
{
    $message = WorkerMarkdownTerminalProgressMessage( $document_id, $batch_start, $batch_end, $pages_count );

    WorkerLog( WORKER_INFO, $message, $document_id, false, false, 1 );
}



/*
 *
 *  Build the relative chunks directory path for the manifest
 *
 */

function WorkerMarkdownRelativeChunksDirectory( $document_id )
{
    return 'docs/' . ((int)($document_id / 100)) . '/' . $document_id . '/' . $document_id . '.chunks/';
}



/*
 *
 *  Build the markdown chunk definitions for a document
 *
 */

function WorkerMarkdownChunkDefinitions( $document_id, $pages_count )
{
    $chunks = [];

    for( $core_start = 0; $core_start < $pages_count; $core_start += WORKER_MARKDOWN_CHUNK_CORE )
    {
        $core_end = $core_start + WORKER_MARKDOWN_CHUNK_CORE - 1;
        if( $core_end >= $pages_count )
        {
            $core_end = $pages_count - 1;
        }

        $page_start = $core_start - WORKER_MARKDOWN_CHUNK_BACK;
        if( $page_start < 0 )
        {
            $page_start = 0;
        }

        $page_end = $core_end + WORKER_MARKDOWN_CHUNK_FORWARD;
        if( $page_end >= $pages_count )
        {
            $page_end = $pages_count - 1;
        }

        $chunks[] = [
            'file' => basename( PathToMarkdownChunk( $document_id, $page_start, $page_end ) ),
            'page_start' => $page_start,
            'page_end' => $page_end,
            'core_start' => $core_start,
            'core_end' => $core_end,
            'page_count' => $page_end - $page_start + 1,
        ];
    }

    return $chunks;
}



/*
 *
 *  Compute the region of a page within a chunk
 *
 */

function WorkerMarkdownPageRegion( $page, $chunk )
{
    if( $page < $chunk['core_start'] )
    {
        return 'back_overlap';
        /*--- EXIT POINT ---*/
    }

    if( $page > $chunk['core_end'] )
    {
        return 'forward_overlap';
        /*--- EXIT POINT ---*/
    }

    return 'core';
}



/*
 *
 *  Write a file atomically inside the document repository
 *
 */

function WorkerMarkdownWriteFileAtomically( $path, $content, $document_id )
{
    $tmp_path = $path . '.tmp';

    $written = file_put_contents( $tmp_path, $content );
    if( $written === false )
    {
        WorkerLog( WORKER_WARNING, "Failed writing markdown file $tmp_path", $document_id, true, true, true );
        return false;
        /*--- EXIT POINT ---*/
    }

    $renamed = rename( $tmp_path, $path );
    if( $renamed === false )
    {
        WorkerLog( WORKER_WARNING, "Failed renaming markdown file $tmp_path -> $path", $document_id, true, true, true );
        return false;
        /*--- EXIT POINT ---*/
    }

    return true;
}



/*
 *
 *  Read one markdown page from the page cache
 *
 */

function WorkerMarkdownReadPage( $document_id, $page )
{
    $path = PathToMarkdownPage( $document_id, $page );

    if( ! FileExists( $path ) )
    {
        WorkerLog( WORKER_WARNING, "Markdown page not found: $path", $document_id, true, true, true );
        return false;
        /*--- EXIT POINT ---*/
    }

    return file_get_contents( $path );
}



/*
 *
 *  Build the markdown text of one chunk from cached pages
 *
 */

function WorkerMarkdownBuildChunkMarkdown( $document_id, $chunk )
{
    $lines = [];

    $lines[] = '<!-- pinaxo-chunk:begin document_id="' . $document_id . '" file="' . $chunk['file'] . '" page_start="' . str_pad( (string)$chunk['page_start'], 4, '0', STR_PAD_LEFT ) . '" page_end="' . str_pad( (string)$chunk['page_end'], 4, '0', STR_PAD_LEFT ) . '" core_start="' . str_pad( (string)$chunk['core_start'], 4, '0', STR_PAD_LEFT ) . '" core_end="' . str_pad( (string)$chunk['core_end'], 4, '0', STR_PAD_LEFT ) . '" -->';
    $lines[] = '';

    for( $page = $chunk['page_start']; $page <= $chunk['page_end']; $page++ )
    {
        $page_markdown = WorkerMarkdownReadPage( $document_id, $page );
        if( $page_markdown === false )
        {
            return false;
            /*--- EXIT POINT ---*/
        }

        $page_markdown = rtrim( $page_markdown );
        $page_str = str_pad( (string)$page, 4, '0', STR_PAD_LEFT );
        $region = WorkerMarkdownPageRegion( $page, $chunk );

        $lines[] = '<!-- pinaxo-page:begin document_id="' . $document_id . '" page="' . $page_str . '" region="' . $region . '" -->';
        $lines[] = '## Page ' . $page_str;

        if( $page_markdown !== '' )
        {
            $lines[] = $page_markdown;
        }

        $lines[] = '<!-- pinaxo-page:end document_id="' . $document_id . '" page="' . $page_str . '" -->';
        $lines[] = '';
    }

    $lines[] = '<!-- pinaxo-chunk:end document_id="' . $document_id . '" file="' . $chunk['file'] . '" -->';
    $lines[] = '';

    return implode( "\n", $lines );
}



/*
 *
 *  Build the manifest payload for markdown chunks
 *
 */

function WorkerMarkdownBuildManifest( $document_id, $pages_count, $chunks )
{
    $docling_version = MarkdownDoclingVersion();
    if( $docling_version === false )
    {
        $docling_version = '';
    }

    return [
        'manifest_version' => '1.0',
        'document_id' => (int)$document_id,
        'page_count' => (int)$pages_count,
        'page_index_base' => 0,
        'page_end_inclusive' => true,
        'generator' => [
            'name' => 'Worker2026',
            'version' => WORKER_VERSION,
            'docling_version' => $docling_version,
        ],
        'chunking' => [
            'strategy' => 'core-plus-overlap',
            'core_size' => WORKER_MARKDOWN_CHUNK_CORE,
            'back_overlap' => WORKER_MARKDOWN_CHUNK_BACK,
            'forward_overlap' => WORKER_MARKDOWN_CHUNK_FORWARD,
            'filename_template' => '{document_id:06d}.{page_start:04d}-{page_end:04d}.chunk.md',
        ],
        'storage' => [
            'relative_dir' => WorkerMarkdownRelativeChunksDirectory( $document_id ),
            'chunk_markers_spec' => 'chunk-markers.md',
        ],
        'generated_at' => gmdate( 'Y-m-d\\TH:i:s\\Z' ),
        'chunks' => $chunks,
    ];
}



/*
 *
 *  Build every markdown chunk and the manifest from the cached pages
 *
 */

function WorkerMarkdownBuildChunks( $document_id, $pages_count )
{
    $chunks = WorkerMarkdownChunkDefinitions( $document_id, $pages_count );

    RemoveMarkdownChunks( $document_id );
    MakePathToMarkdownChunksMaybe( $document_id );

    foreach( $chunks as $chunk )
    {
        $chunk_markdown = WorkerMarkdownBuildChunkMarkdown( $document_id, $chunk );
        if( $chunk_markdown === false )
        {
            return false;
            /*--- EXIT POINT ---*/
        }

        $chunk_path = PathToMarkdownChunk( $document_id, $chunk['page_start'], $chunk['page_end'] );
        $result = WorkerMarkdownWriteFileAtomically( $chunk_path, $chunk_markdown, $document_id );
        if( ! $result )
        {
            return false;
            /*--- EXIT POINT ---*/
        }
    }

    $manifest = WorkerMarkdownBuildManifest( $document_id, $pages_count, $chunks );
    $manifest_json = json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    $manifest_json .= "\n";

    $result = WorkerMarkdownWriteFileAtomically( PathToMarkdownManifest( $document_id ), $manifest_json, $document_id );
    if( ! $result )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    return true;
}



/*
 *
 *  Initialize markdown repository state for a new PDF generation job
 *
 */

function WorkerMarkdownInitialize( $document )
{
    $document_id = $document['id'];

    WorkerLog( WORKER_INFO, 'Initializing markdown pages/chunks', $document_id, true, false, false );

    RemoveMarkdownChunks( $document_id );

    MakePathToMarkdownPagesMaybe( $document_id );
    MakePathToMarkdownChunksMaybe( $document_id );

    DbDocumentMarkdownStateUpdate( $document_id, $document['md_md5'], -1, WORKER_MARKDOWN_LOCK );

    $document['md_page_index'] = -1;
    $document['lock'] = WORKER_MARKDOWN_LOCK;

    return $document;
}



/*
 *
 *  Render the next batch of markdown pages through Docling
 *
 */

function WorkerMarkdownRenderNextBatch( $document )
{
    $document_id = $document['id'];
    $batch_start = WorkerMarkdownBatchStart( $document );
    $batch_end = WorkerMarkdownBatchEnd( $document );
    $expected_pages = $batch_end - $batch_start + 1;

    WorkerMarkdownLogTerminalProgress( $document_id, $batch_start, $batch_end, $document['pages_count'] );

    MakePathToMarkdownPagesMaybe( $document_id );

    $result = MarkdownExportPagesWithDocling( $document_id, $batch_start, $batch_end );

    if( $result['page_start_zero'] != $batch_start || $result['page_end_zero'] != $batch_end )
    {
        WorkerLog( WORKER_WARNING, 'Docling returned an unexpected markdown page range', $document_id, true, true, true );
        return false;
        /*--- EXIT POINT ---*/
    }

    if( $result['page_count'] != $expected_pages || $result['pages_written'] != $expected_pages )
    {
        WorkerLog( WORKER_WARNING, 'Docling returned an unexpected markdown pages count', $document_id, true, true, true );
        return false;
        /*--- EXIT POINT ---*/
    }

    DbDocumentMarkdownStateUpdate( $document_id, $document['md_md5'], $batch_end, WORKER_MARKDOWN_LOCK );

    WorkerMarkdownLogTerminalProgress( $document_id, $batch_start, $batch_end, $document['pages_count'] );

    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Finalize markdown generation by building chunks and manifest
 *
 */

function WorkerMarkdownFinalize( $document )
{
    $document_id = $document['id'];

    WorkerLog( WORKER_INFO, 'Generating markdown chunks', $document_id, true, false, false );

    $result = WorkerMarkdownBuildChunks( $document_id, $document['pages_count'] );
    if( ! $result )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    DbDocumentMarkdownStateUpdate( $document_id, $document['md5'], $document['pages_count'], '' );

    WorkerLog( WORKER_INFO, 'Markdown generation completed - pages and chunks are aligned with current PDF', $document_id, true, false, false );

    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Generate markdown page cache and derived chunks for one document
 *
 */

function WorkerMarkdown()
{
    $brand_ids = WorkerMarkdownBrandIdsSql();
    if( $brand_ids === false )
    {
        return WORKER_MARKDOWN_RESULT_NO_WORK;
        /*--- EXIT POINT ---*/
    }

    $document = DbDocumentToMarkdown( $brand_ids );
    if( $document === false )
    {
        return WORKER_MARKDOWN_RESULT_NO_WORK;
        /*--- EXIT POINT ---*/
    }

    $document_id = $document['id'];

    ConsistencyCheck( $document_id, false, false );

    if( WorkerMarkdownNeedsInitialization( $document ) )
    {
        $document = WorkerMarkdownInitialize( $document );
    }

    if( $document['md_page_index'] < $document['pages_count'] - 1 )
    {
        $result = WorkerMarkdownRenderNextBatch( $document );
        if( $result === false )
        {
            return false;
            /*--- EXIT POINT ---*/
        }

        return WORKER_MARKDOWN_RESULT_BATCH_PROCESSED;
        /*--- EXIT POINT ---*/
    }

    if( $document['md_page_index'] == $document['pages_count'] - 1 )
    {
        $result = WorkerMarkdownFinalize( $document );
        if( $result === false )
        {
            return false;
            /*--- EXIT POINT ---*/
        }

        return WORKER_MARKDOWN_RESULT_COMPLETED;
        /*--- EXIT POINT ---*/
    }

    return WORKER_MARKDOWN_RESULT_NO_WORK;
}
