<?php

/*
 *
 *
 *  Paths & filenames
 *
 *
 */



/*
 *
 *  NOTE: images extension is ".jpg", short and lowercase.
 *
 */

/*
 *
 *  Path to the parent directory of the root directory of a document (the directory that contains up to 100 documents)
 *
 */

function PathToDocumentParentFolder( $document_id )
{
    return MASTER_STORAGE_DIR . '/docs/' . ((int)($document_id / 100)) . '/';
}



/*
 *
 *  Path to the root folder of a document (contains the pdf, pdfff, cover(s), and cache directory
 *
 */

function PathToDocument( $document_id )
{
    return MASTER_STORAGE_DIR . '/docs/' . ((int)($document_id / 100)) . '/' . $document_id . '/';
}



/*
 *
 *  Path to the root folder of a document in slave/backup volume (contains the pdf, pdfff, cover(s), and cache directory
 *
 */

function PathToDocumentInSlave( $document_id )
{
    return SLAVE_STORAGE_DIR . '/docs/' . ((int)($document_id / 100)) . '/' . $document_id . '/';
}



/*
 *
 *  Path to the pdf
 *
 */

function PathToPdf( $document_id )
{
    return PathToDocument( $document_id ) . $document_id . '.pdf';
}



/*
 *
 *  Path to the pdfff
 *
 */

function PathToPdfff( $document_id )
{
    return PathToDocument( $document_id ) . $document_id . '.pdfff';
}



/*
 *
 *  Path to the pdfidx
 *
 */

function PathToPdfidx( $document_id )
{
    return PathToDocument( $document_id ) . $document_id . '.pdfidx';
}



/*
 *
 *  Path to the cover image (at a given and allowed resolution)
 *
 */

function PathToCover( $document_id, $dpi )
{
    $resolutions = CoverResolutions();

    foreach( $resolutions as $res )
    {
        if( $res['dpi'] == $dpi )
        {
            if( $dpi <= 216 )
            {
                return PathToDocument( $document_id ) . $document_id . '.cover.dpi' . $dpi . '.jpg';
                /*--- EXIT POINT ---*/
            }
            else
            {
                return PathToDocument( $document_id ) . $document_id . '.cover.px' . $dpi . '.jpg';
                /*--- EXIT POINT ---*/
            }
        }
    }

    WorkerLog( WORKER_ERROR, 'FATAL - PathToCover: invalid resolution or size', $document_id, true, true, true );
    WorkerQuitNow();
    /*--- QUIT POINT ---*/
}



/*
 *
 *  Path to the cache V1 directory
 *
 */

function PathToCacheV1( $document_id )
{
    return PathToDocument( $document_id ) . $document_id . '.cache/';
}



/*
 *
 *  Path to the cachev2 directory
 *
 */

function PathToCacheV2( $document_id )
{
    return PathToDocument( $document_id ) . $document_id . '.cache_v2/';
}



/*
 *
 *  Path to meta
 *
 */

function PathToMeta( $document_id )
{
    return PathToDocument( $document_id ) . $document_id . '.meta/';
}



/*
 *
 *  Path to "quick" meta
 *
 */

function PathToQuickMeta( $document_id )
{
    return PathToDocument( $document_id ) . $document_id . ".meta/$document_id.meta.json";
}



/*
 *
 *  Path to the markdown pages directory
 *
 */

function PathToMarkdownPages( $document_id )
{
    return PathToDocument( $document_id ) . $document_id . '.pagesmd/';
}



/*
 *
 *  Path to one markdown page file
 *
 */

function PathToMarkdownPage( $document_id, $page )
{
    return PathToMarkdownPages( $document_id ) . $document_id . '.' . str_pad( (string)$page, 4, '0', STR_PAD_LEFT ) . '.page.md';
}



/*
 *
 *  Path to the obsolete markdown chunks directory
 *
 */

function PathToMarkdownChunks( $document_id )
{
    return PathToDocument( $document_id ) . $document_id . '.chunks/';
}



/*
 *
 *  Path to the sub-cache v2 directory (inside the cache directory) that contains the images at a given and allowed dpi
 *
 */

function PathToImagesV2( $document_id, $dpi )
{
    $pack = PackForResolutionV2( $dpi );

    if( $pack == 1 )
    {
        $path = PathToCacheV2( $document_id ) . $document_id . '.cache.dpi' . $dpi . '/';
    }
    else
    {
        $path = PathToCacheV2( $document_id ) . $document_id . '.cache.dpi' . $dpi . '.pack' . $pack . '/';
    }

    return $path;
}



/*
 *
 *  Path to image for a given and allowed resolution
 *
 */

function PathToPageImageV2( $document_id, $dpi, $page )
{
    $pack = PackForResolutionV2( $dpi );
    $format = FormatForResolutionV2( $dpi );

    if( $pack == 1 )
    {
        $path = PathToImagesV2( $document_id, $dpi ) . $document_id . '.cache.dpi' . $dpi . '.page' . $page . '.' . $format;
    }
    else
    {
        $packpage = $page - ( $page % $pack );

        $path = PathToImagesV2( $document_id, $dpi ) . $document_id . '.cache.dpi' . $dpi . '.pack' . $pack . '.page' . $packpage . '.' . $format;
    }

    return $path;
}



/*
 *
 *  Returns true if the path is safe
 *  A safe path points to a document directory
 *  or to files or directories inside a document directory
 *
 */

function PathIsSafe( $path, $document_id )
{
    $document_id = (string)$document_id;

    if( $document_id === '' || ( ! ctype_digit( $document_id ) ) || $document_id == 0 )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $safepath = PathToDocument( $document_id );

    $safe = StringBegins( $path, $safepath );

    if( ( $safe === false ) && SlaveIsPresent() )
    {
        $safepath = PathToDocumentInSlave( $document_id );

        $safe = StringBegins( $path, $safepath );
    }

    return $safe;
}



/*
 *
 *  According to the above naming patterns if the specified filename is a cover, then the resolution is returned, zero otherwise
 *
 */

function CoverDpiFromFilename( $document_id, $filename )
{
    $path = PathToDocument( $document_id ) . $filename;

    $resolutions = CoverResolutions();

    foreach( $resolutions as $res )
    {
        $match = PathToCover( $document_id, $res['dpi'] );
        if( $path == $match )
        {
            return $res['dpi'];
            /*--- EXIT POINT ---*/
        }
    }

    return 0;
}



/*
 *
 *  According to the above naming patterns if the specified full path points to a document directory, pdf, page image or cover returns the document_id, zero otherwise
 *
 */

function DocumentIdFromPath( $path )
{
    $path_parts = pathinfo( $path );

    $filename = $path_parts['basename'];

    $filename_parts = explode( '.', $filename );

    $document_id = $filename_parts[ 0 ];

    if( ! ctype_digit( $document_id ) )
    {
        $document_id = 0;
    }

    return (int)$document_id;
}



/*
 *
 *  Path to brand directory, make it if missing
 *
 */

function PathToBrandDirectory( $brand_id )
{
    $path = MASTER_STORAGE_DIR . "/brands/$brand_id/";

    if( ! FSDirectoryExists( $path ) )
    {
        FSMakeDir( $path );
    }

    return $path;
}



/*
 *
 *  Path to brand directory in backup/slave, make it if missing
 *
 */

function PathToBrandDirectoryInSlave( $brand_id )
{
    $path = SLAVE_STORAGE_DIR . "/brands/$brand_id/";

    if( ! FSDirectoryExists( $path ) )
    {
        FSMakeDir( $path );
    }

    return $path;
}

