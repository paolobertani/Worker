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
 *  Initialize markdown repository state for a new PDF generation job
 *
 */

function WorkerMarkdownInitialize( $document )
{
    $document_id = $document['id'];

    WorkerLog( WORKER_INFO, 'Initializing markdown pages', $document_id, true, false, false );

    RemoveMarkdownChunks( $document_id );

    RemoveMarkdownPages( $document_id );

    MakePathToMarkdownPagesMaybe( $document_id );

    DbDocumentMarkdownStateUpdate( $document_id, 0, $document['md_md5'], -1, WORKER_MARKDOWN_LOCK );

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

    if( $batch_end == $document['pages_count'] - 1 )
    {
        RemoveMarkdownChunks( $document_id );

        DbDocumentMarkdownStateUpdate( $document_id, 1, $document['md5'], $document['pages_count'], '' );
        WorkerLog( WORKER_INFO, 'Markdown generation completed - pages are aligned with current PDF', $document_id, true, false, false );
        WorkerAlive();

        return WORKER_MARKDOWN_RESULT_COMPLETED;
        /*--- EXIT POINT ---*/
    }

    DbDocumentMarkdownStateUpdate( $document_id, 0, $document['md_md5'], $batch_end, WORKER_MARKDOWN_LOCK );

    WorkerMarkdownLogTerminalProgress( $document_id, $batch_start, $batch_end, $document['pages_count'] );

    WorkerAlive();

    return WORKER_MARKDOWN_RESULT_BATCH_PROCESSED;
}



/*
 *
 *  Finalize markdown generation after all pages have been rendered
 *
 */

function WorkerMarkdownFinalize( $document )
{
    $document_id = $document['id'];

    RemoveMarkdownChunks( $document_id );

    DbDocumentMarkdownStateUpdate( $document_id, 1, $document['md5'], $document['pages_count'], '' );

    WorkerLog( WORKER_INFO, 'Markdown generation completed - pages are aligned with current PDF', $document_id, true, false, false );

    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Generate markdown page cache for one document
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

        return $result;
        /*--- EXIT POINT ---*/
    }

    if( $document['md_page_index'] >= $document['pages_count'] - 1 )
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
