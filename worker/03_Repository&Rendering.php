<?php

/*
 *
 *
 *  Repository management and rendering
 *
 *
 */



/*
 *
 *  Returns true if there is enought free disk space to operate
 *  on both master volume and (if present) slave volume.
 *  Parameters passed by reference are set to the available space
 *  on respective volumes. `availableOnSlave` is always null
 *  when slave volume is not present
 *
 */

function EnoughtDiskSpace( &$availableOnMaster, &$avaliableOnSlave )
{
    $gigabytes = 1000 * 1000 * 1000;

    $availableOnMaster = null;
    $avaliableOnSlave  = null;

    if( ! DirectoryExists( MASTER_STORAGE_DIR ) )
    {
        WorkerLog( WORKER_ERROR, "EnoughtDiskSpace: repository not found", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $availableOnMaster = disk_free_space( MASTER_STORAGE_DIR ) / $gigabytes;

    $enought = ( $availableOnMaster > WORKER_REQUIRED_SPACE );

    $availableOnMaster = StringFromFloat( $availableOnMaster, 1 );

    if( ! SlaveIsPresent() )
    {
        return $enought;
        /*--- EXIT POINT ---*/
    }

    if( ! DirectoryExists( SLAVE_STORAGE_DIR ) )
    {
        WorkerLog( WORKER_ERROR, "EnoughtDiskSpace: repository not found on slave volume", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $availableOnSlave = disk_free_space( SLAVE_STORAGE_DIR ) / $gigabytes;

    $enought = $enought & ( $availableOnSlave > WORKER_REQUIRED_SPACE );

    $availableOnSlave = StringFromFloat( $availableOnSlave, 1 );

    return $enought;
}



/*
 *
 *  Execute a rebuild (upon admin request);
 *  returns true on success, false on failure
 *
 */

function ExecuteCommand( $command, &$document )
{
    $hd = 1;
    $document_id = $document['id'];


    // rebuild pdfff

    if( $command == 'all' || $command == 'pdfff' )
    {
        $result = Pdfff( PathToPdf( $document_id ), PathToPdfff( $document_id ) );
        WorkerAlive();

        if( ! $result )
        {
            return false;
            /*--- EXIT POINT ---*/
        }

        $document['idrolab_status'] = 0; // let the document be retagged if needed
    }


    // rebuild pdfidx (is rebuilt when pdfff is issued too)

    if( $command == 'all' || $command == 'pdfff' || $command == 'pdfidx' )
    {
        Pdfidx( PathToPdfff( $document_id ), PathToPdfidx( $document_id ) );
    }


    // rebuild covers

    if( $command == 'all' || $command == 'covers' || $command == 'cache' )
    {
        RemoveCovers( $document_id );
        $result = RenderCoversForEachResolution( $document_id, $hd );
        if( ! $result )
        {
            return false;
            /*--- EXIT POINT ---*/
        }
    }


    // rebuild cache

    if( $command == 'all' || $command == 'cache' )
    {
        RemoveCacheV2( $document_id );
        $document['cachev2_md5'] = '';
    }


    // verify command is supported

    if( ! CommandSupported( $command ) )
    {
        WorkerLog( WORKER_WARNING, "Ignored unkwnown command: $command", $document_id, true, true, true );
    }

    return true;
}



/*
 *
 *  Is the command supported?
 *
 */

function CommandSupported( $command )
{
    $commands = [ 'all', 'pdfff', 'pdfidx', 'covers', 'cache' ];
    $supported = in_array( $command, $commands );
    return $supported;
}



/*
 *
 *  Checks minimal requirements, breaks loud if not met.
 *
 */

function ConsistencyCheck( $document_id, $require_pdfff = true, $require_pdfidx = true )
{
    if( ! DirectoryExists( MASTER_STORAGE_DIR ) )
    {
        WorkerLog( WORKER_ERROR, "ConsistencyCkeck: repository not found", $document_id, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( ! DirectoryExists( PathToDocument( $document_id ) ) )
    {
        WorkerLog( WORKER_ERROR, "ConsistencyCkeck: document directory not found", $document_id, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( ! FileExists( PathToPdf( $document_id ) ) )
    {
        WorkerLog( WORKER_ERROR, "ConsistencyCkeck: pdf not found", $document_id, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    //  if( ! IsPdf( PathToPdf( $document_id ) ) )
    //  {
    //      WorkerLog( WORKER_ERROR, "ConsistencyCkeck: document is not a pdf", $document_id, true, true, true );
    //      WorkerQuitNow();
    //      /*--- QUIT POINT ---*/
    //  }

    if( $require_pdfff && ! FileExists( PathToPdfff( $document_id ) ) )
    {
        WorkerLog( WORKER_ERROR, "ConsistencyCkeck: pdfff not found", $document_id, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( $require_pdfidx && ! FileExists( PathToPdfidx( $document_id ) ) )
    {
        WorkerLog( WORKER_ERROR, "ConsistencyCkeck: pdfidx not found", $document_id, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



/*
 *
 *  Make path to cachev2 if not present
 *
 */

function MakePathToCacheV2Maybe( $document_id )
{
    if( ! DirectoryExists( PathToCacheV2( $document_id ) ) )
    {
        MakeDirectoryTree( PathToCacheV2( $document_id ) );
    }
}



/*
 *
 *  Make path to meta dir if not present
 *
 */

function MakePathToMetaMaybe( $document_id )
{
    if( ! DirectoryExists( PathToMeta( $document_id ) ) )
    {
        MakeDirectoryTree( PathToMeta( $document_id ) );
    }
}



/*
 *
 *  Make path to markdown pages dir if not present
 *
 */

function MakePathToMarkdownPagesMaybe( $document_id )
{
    if( ! DirectoryExists( PathToMarkdownPages( $document_id ) ) )
    {
        MakeDirectoryTree( PathToMarkdownPages( $document_id ) );
    }
}



/*
 *
 *  Make path to markdown chunks dir if not present
 *
 */

function MakePathToMarkdownChunksMaybe( $document_id )
{
    if( ! DirectoryExists( PathToMarkdownChunks( $document_id ) ) )
    {
        MakeDirectoryTree( PathToMarkdownChunks( $document_id ) );
    }
}



/*
 *
 *  Render the pages in the interval at each resolution (a packed group is rendered when is requested rendering of the first page)
 *
 */

function RenderPagesForEachResolutionV2( $document_id, $start, $end, $pagesCount )
{
    $resolutions = CachedResolutionsV2();

    ArraySortByKey( $resolutions, 'dpiDESC' );

    foreach( $resolutions as $res )
    {
        $result = RenderPagesAtResolutionV2( $document_id, $res['dpi'], $start, $end, $pagesCount );
        if( ! $result )
        {
            return false;
            /*--- EXIT POINT ---*/
        }
    }

    return true;
}



/*
 *
 *  Render the cover at each resolution
 *
 */

function RenderCoversForEachResolution( $document_id, $hd )
{
    $resolutions = CoverResolutions();

    ArraySortByKey( $resolutions, 'dpiDESC' );

    foreach( $resolutions as $res )
    {
        $result = RenderCover( $document_id, $res['dpi'], $hd );
        if( ! $result )
        {
            return false;
            /*--- EXIT POINT ---*/
        }

        WorkerAlive();
        WorkerQuitMaybe();
    }
    return true;
}



/*
 *
 *  Remove all files matching cover naming pattern
 *
 */

function RemoveCovers( $document_id )
{
    $files = FilesInDirectory( PathToDocument( $document_id ) );

    foreach( $files as $f )
    {
        $dpi = CoverDpiFromFilename( $document_id, $f );

        if( $dpi != 0 )
        {
            RemoveFile( PathToDocument( $document_id ) . $f, $document_id );
        }
    }
}



/*
 *
 *  Remove all the cache v2 pages
 *
 */

function RemoveCacheV2( $document_id )
{
    $cache = PathToCacheV2( $document_id );
    if( DirectoryExists( $cache ) )
    {
        RemoveDirectory( $cache, $document_id );
    }
}



/*
 *
 *  Remove markdown pages directory and its files
 *
 */

function RemoveMarkdownPages( $document_id )
{
    $pages = PathToMarkdownPages( $document_id );

    if( DirectoryExists( $pages ) )
    {
        RemoveDirectory( $pages, $document_id );
    }
}



/*
 *
 *  Remove markdown chunks directory and its files
 *
 */

function RemoveMarkdownChunks( $document_id )
{
    $chunks = PathToMarkdownChunks( $document_id );

    if( DirectoryExists( $chunks ) )
    {
        RemoveDirectory( $chunks, $document_id );
    }
}



/*
 *
 *  Render a cover at a given dpi
 *
 */

function RenderCover( $document_id, $dpi, $hd )
{
    $quality = CoverQualityForResolution( $dpi, $hd );

    $pdf = PathToPdf( $document_id );

    $cover = PathToCover( $document_id, $dpi );

    if( $dpi <= 216 )
    {
        $milliseconds = PdfJpg( $pdf, $dpi, 0, $cover, $quality );
        return $milliseconds !== false;
        /*--- EXIT POINT ---*/
    }

    // the dpi parameter specifies pixel width (not resolution)

    $width = (int)$dpi;

    $coverMeasure = DocumentCoverMeasure( $document_id );

    $dpi = ( $width + 1 ) * 72.0 / $coverMeasure['width']; // some bleed is added
    $height = (int)( $coverMeasure['height'] / $coverMeasure['width'] * $width );

    $milliseconds = PdfJpg( $pdf, $dpi, 0, $cover, $quality, 1, 0, 0, $width, $height );
    return $milliseconds !== false;
}



/*
 *
 *  Render all the pages in the interval at a given dpi for cache v2
 *
 */

function RenderPagesAtResolutionV2( $document_id, $dpi, $start, $end, $pagesCount )
{
    $pagesDir = PathToImagesV2( $document_id, $dpi );

    if( ! DirectoryExists( $pagesDir ) )
    {
        MakeDirectoryTree( $pagesDir );
    }

    $getColor = GetColorForResolutionV2( $dpi );
    $quality = QualityForResolutionV2( $dpi );
    $format = FormatForResolutionV2( $dpi );
    $pack = PackForResolutionV2( $dpi );
    $pdf = PathToPdf( $document_id );

    if( ! WORKER_CACHE_MAKES_COLORS )
    {
        $getColor = false;
    }

    for( $pageNum = $start; $pageNum <= $end; $pageNum++ )
    {
        if( $pageNum % $pack !== 0 ) continue;

        if( $pageNum + $pack > $pagesCount )
        {
            $pack = $pagesCount - $pageNum;
        }

        $img = PathToPageImageV2( $document_id, $dpi, $pageNum );

        $result = PdfJpgV2( $pdf, $dpi, $pageNum, $img, $quality, $pack, $getColor );

        if( $result === false )
        {
            return false;
            /*--- EXIT POINT ---*/
        }

        if( $getColor )
        {
            $color = $result;
            file_put_contents( PathToDocument( $document_id ) . "$document_id.pagescolor.txt", $color, FILE_APPEND );
        }

        WorkerQuitMaybe();
        WorkerAlive();
    }

    return true;
}



/*
 *
 *  Build Pdfff and Pdfidx for a inbox sent document
 *
 */

function SentDocumentPdfffPdfidx( $sent_document_id )
{
    $result = Pdfff( PATH_TO_INBOX . "$sent_document_id.pdf", PATH_TO_INBOX . "$sent_document_id.pdfff" );

    if( ! $result )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    Pdfidx( PATH_TO_INBOX . "$sent_document_id.pdfff", PATH_TO_INBOX . "$sent_document_id.pdfidx" );

    return true;
}

