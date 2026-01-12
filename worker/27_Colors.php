<?php

//
//
// Pages color string generation
//
//



//
// Generate metadata
//

function PagesColorString( $document )
{
    if( WORKER_CACHE_MAKES_COLORS )
    {
        return;
    }

    $colors = '';

    $pages_count = $document['pages_count'];

    $document_id = $document['id'];

    // Choose a unpacked resolution to pick the image to get the color from

    $resolutions = CachedResolutionsV2();
    $dpi = false;
    foreach( $resolutions as $r )
    {
        if( $r['pack'] === 1 && $r['getcolor'] )
        {
            $dpi = $r['dpi'];
        }
    }
    if( $dpi === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - PagesColorString: no resolution choosen to pick image to get color from", $document_id, true, true, true );
    }

    for( $page = 0; $page < $pages_count; $page++ )
    {
        $path = PathToPageImageV2( $document_id, $dpi, $page );
        if( ! is_file( $path ) )
        {
            return false;
            /*--- EXIT POINT ---*/
        }

        $color = PagesColorGetFromFile( $path );
        if( $color === false )
        {
            return false;
            /*--- EXIT POINT ---*/
        }

        $colors .= $color;

        WorkerAlive();
    }

    return $colors;
}



function PagesColorGetFromFile( $path )
{
    $img = @imagecreatefromstring( file_get_contents( $path ) );
    if( $img === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $x = imagesx( $img );
    $y = imagesy( $img );
    $tmp_img = ImageCreateTrueColor( 1,1 );
    ImageCopyResampled( $tmp_img,$img,0,0,0,0,1,1,$x,$y );
    $rgb = ImageColorAt( $tmp_img,0,0 );
    $r = ( $rgb >> 16 ) & 0xFF;
    $g = ( $rgb >> 8  ) & 0xFF;
    $b = ( $rgb       ) & 0xFF;

    $color = sprintf( "%02x%02x%02x", $r, $g, $b );

    unset( $img );
    unset( $tmp_img );

    return $color;
}
