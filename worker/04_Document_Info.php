<?php

/*
 *
 *
 *  Document info retrieved inspecting the Repository
 *
 *
 */


/*
 *
 *  Returns the 'pdf' value: 1 = the pdf exists, 0 = the pdf is not present, -1 the file is not a pdf.
 *
 */

function DocumentPdf( $document_id )
{
    $pdf = 0;

    if( FileExists( PathToPdf( $document_id ) ) )
    {
        if( IsPdf( PathToPdf( $document_id ) ) )
        {
            $pdf = 1;
        }
        else
        {
            $pdf = -1;
        }
    }

    return $pdf;
}



/*
 *
 *  Returns the MD5 given the document_id; empty string if the file is missing
 *
 */

function DocumentMd5( $document_id )
{
    if( FileExists( PathToPdfff( $document_id ) ) )
    {
        $md5 = Md5FromPdfff( PathToPdfff( $document_id ) );
    }
    elseif( FileExists( PathToPdf( $document_id ) ) )
    {
        $md5 = Md5OfFile( PathToPdf( $document_id ) );
    }
    else
    {
        $md5 = '';
    }

    return $md5;
}



/*
 *
 *  Returns the 'pdf_size' value
 *
 */

function DocumentPdfSize( $document_id )
{
    $size = 0;

    if( FileExists( PathToPdf( $document_id ) ) )
    {
        $size = GetFileSize( PathToPdf( $document_id ) );
    }

    return $size;
}



/*
 *
 *  Returns the 'pdfff_size' value
 *
 */

function DocumentPdfffSize( $document_id )
{
    $size = 0;

    if( FileExists( PathToPdfff( $document_id ) ) )
    {
        $size = GetFileSize( PathToPdfff( $document_id ) );
    }

    return $size;
}



/*
 *
 *  Returns the 'pdfidx_size' value
 *
 */

function DocumentPdfidxSize( $document_id )
{
    $size = 0;

    if( FileExists( PathToPdfidx( $document_id ) ) )
    {
        $size = GetFileSize( PathToPdfidx( $document_id ) );
    }

    return $size;
}



/*
 *
 *  Returns the 'cache_size' value
 *
 */

function DocumentCacheSize( $document_id )
{
    $size = 0;

    if( DirectoryExists( PathToCacheV1( $document_id ) ) )
    {
        $size += GetDirectorySize( PathToCacheV1( $document_id ) );
    }

    if( DirectoryExists( PathToCacheV2( $document_id ) ) )
    {
        $size += GetDirectorySize( PathToCacheV2( $document_id ) );
    }

    return $size;
}



/*
 *
 *  Returns the width and height of the cover at 72 dpi ( NOT the bytes size )
 *
 */

function DocumentCoverMeasure( $document_id )
{
    $f = false;
    $measure = [ 'width' => 0.0, 'height' => 0.0 ];

    if( FileExists( PathToPdfff( $document_id ) ) )
    {
        $f = PathToPdfff( $document_id );
    }
    elseif( FileExists( PathToPdf( $document_id ) ) )
    {
        $f = PathToPdf( $document_id );
    }

    if( $f !== false )
    {
        $result = PdfSize( $f, '', '', 0 );

        $measure['width']  = $result[0]['sz'][0]['w'];
        $measure['height'] = $result[0]['sz'][0]['h'];
    }
    else
    {
        WorkerLog( WORKER_ERROR, "FATAL - DocumentCoverMeasure: pdf and pdfff both not available", $document_id, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return $measure;
}