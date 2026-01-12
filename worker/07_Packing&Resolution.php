<?php

//
//
// Packing and Resolutions
//
//


//
// Packing and quality for each cached v2 resolution
//

function CachedResolutionsV2()
{
    return [
            [ 'dpi' =>  12, 'pack' =>  16, 'quality' =>  0.80, 'format' => 'jpg', 'getcolor' => false ],
            [ 'dpi' =>  20, 'pack' =>   9, 'quality' =>  0.80, 'format' => 'jpg', 'getcolor' => false ],
            [ 'dpi' =>  32, 'pack' =>   4, 'quality' =>  0.80, 'format' => 'jpg', 'getcolor' => false ],
            [ 'dpi' => 348, 'pack' =>   1, 'quality' =>  0.80, 'format' => 'jpg', 'getcolor' => true  ]
           ];
}



//
// Quality for each cover resolution/size
//

function CoverResolutions()
{
    return [
            [ 'dpi' =>  12, 'quality' =>  0.80 /* 0.75 */, 'hdquality' =>  0.80 /* 0.75 */ ],
            [ 'dpi' =>  20, 'quality' =>  0.80 /* 0.72 */, 'hdquality' =>  0.80 /* 0.72 */ ],
            [ 'dpi' =>  32, 'quality' =>  0.80 /* 0.70 */, 'hdquality' =>  0.80 /* 0.70 */ ],
            [ 'dpi' =>  52, 'quality' =>  0.80 /* 0.65 */, 'hdquality' =>  0.80 /* 0.70 */ ],
            [ 'dpi' =>  82, 'quality' =>  0.80 /* 0.60 */, 'hdquality' =>  0.80 /* 0.70 */ ],
            //--------------- over 216 `dpi` value is cover width in pixels --------------//
            [ 'dpi' => 236, 'quality' =>  0.80 /* 0.70 */, 'hdquality' =>  0.80 /* 0.72 */ ],
            [ 'dpi' => 472, 'quality' =>  0.80 /* 0.65 */, 'hdquality' =>  0.80 /* 0.70 */ ],
            [ 'dpi' => 944, 'quality' =>  0.80 /* 0.65 */, 'hdquality' =>  0.80 /* 0.70 */ ]
           ];
}



//
// Returns how many pages are packed in each image for a given resolution in cache v2
//

function PackForResolutionV2( $dpi, $raiseError = true )
{
    $resolutions = CachedResolutionsV2();

    foreach( $resolutions as $res )
    {
        if( $res['dpi'] == $dpi )
        {
            return $res['pack'];
            /*--- EXIT POINT ---*/
        }
    }

    if( $raiseError )
    {
        WorkerLog( WORKER_ERROR, 'FATAL - PackForResolution: invalid resolution', null, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return 0;
}



//
// Returns the image format for a given resolution in cache v2
//

function FormatForResolutionV2( $dpi, $raiseError = true )
{
    $resolutions = CachedResolutionsV2();

    foreach( $resolutions as $res )
    {
        if( $res['dpi'] == $dpi )
        {
            return $res['format'];
            /*--- EXIT POINT ---*/
        }
    }

    if( $raiseError )
    {
        WorkerLog( WORKER_ERROR, 'FATAL - PackForResolution: invalid resolution', null, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return 0;
}



//
// Returns whether the average page color has to be computed when rendering the page at a given resolution in cache v2
//

function GetColorForResolutionV2( $dpi, $raiseError = True )
{
    $resolutions = CachedResolutionsV2();

    foreach( $resolutions as $res )
    {
        if( $res['dpi'] == $dpi )
        {
            return $res['getcolor'];
            /*--- EXIT POINT ---*/
        }
    }

    if( $raiseError )
    {
        WorkerLog( WORKER_ERROR, 'FATAL - QualityForResolution: invalid resolution', null, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return 0;
}



//
// Returns the jpeg quality level for a given resolution in cache v2
//

function QualityForResolutionV2( $dpi, $raiseError = True )
{
    $resolutions = CachedResolutionsV2();

    foreach( $resolutions as $res )
    {
        if( $res['dpi'] == $dpi )
        {
            return $res['quality'];
            /*--- EXIT POINT ---*/
        }
    }

    if( $raiseError )
    {
        WorkerLog( WORKER_ERROR, 'FATAL - QualityForResolution: invalid resolution', null, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return 0;
}



//
// Returns the jpeg quality level for a given resolution
//

function QualityForResolution( $dpi, $hd, $raiseError = True )
{
    $resolutions = CachedResolutions();

    foreach( $resolutions as $res )
    {
        if( $res['dpi'] == $dpi )
        {
            return ( $hd == 0 ? $res['quality'] : $res['hdquality'] );
            /*--- EXIT POINT ---*/
        }
    }

    if( $raiseError )
    {
        WorkerLog( WORKER_ERROR, 'FATAL - QualityForResolution: invalid resolution', null, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return 0;
}


//
// Returns the jpeg quality level for the cover image at a given resolution
//



function CoverQualityForResolution( $dpi, $hd, $raiseError = True )
{
    $resolutions = CoverResolutions();

    foreach( $resolutions as $res )
    {
        if( $res['dpi'] == $dpi )
        {
            return ( $hd == 0 ? $res['quality'] : $res['hdquality'] );
            /*--- EXIT POINT ---*/
        }
    }

    if( $raiseError )
    {
        WorkerLog( WORKER_ERROR, 'FATAL - CoverQualityForResolution: invalid resolution', null, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return 0;
}


