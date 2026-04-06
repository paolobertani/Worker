<?php

/*
 *
 *
 *  Extract codes from price list
 *
 *
 */

function ManageTranscode()
{
    // Get a brand with a document to transcode

    $error = '';
    $result = QueryExecute( '76_brand_to_transcode.sql', $error );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 76_brand_to_transcode.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }


    // Exit loop if not found

    if( count( $result ) === 0 )
    {
        return false;
    }


    // brand id et alt.

    $brand_id    = $result[0]['brand_id'];
    $brand       = $result[0]['brand'];


    // Get price list

    $brand_dir = PathToBrandDirectory( $brand_id );
    $price_list_path = $brand_dir . "pricelist.js";

    if( ! FSFileExists( $price_list_path ) )
    {
        WorkerLog( WORKER_WARNING, "Brand $brand ($brand_id) is missing price list, cannot transcode", 0, true, true, true );
        $result = QueryExecute( '77_brand_to_transcode_update.sql', $error, [ 'brand_id' => $brand_id ] );
        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - 77_brand_to_transcode_update.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
        InvalidateCache( 'brands' );
        return false;
        /*--- EXIT POINT ---*/
    }


    // Log

    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Transcoding price list codes on brand $brand ($brand_id)...", 0, false, false, 1 );


    // Load the price list

    $text = file_get_contents( $price_list_path );


    //  Save preamble for later

    $preamble = StringBetween( $text, '', 'products = ' );
    $postabmle = ";\n";
    $text = StringBetween( $text, 'products = ', $postabmle );


    // Turn into assoc array

    $price_list = json_decode( $text, true );

    if( ! is_array( $price_list ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - Cannot json decode price list of brand $brand ($brand_id)...", 0, true, true, true );
        EchoNL( $text );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }


    // Loading transcode info

    $info = json_decode( file_get_contents( $brand_dir . "transcode.info.json" ), true );
    if( $info === null || ! isset( $info['action'] ) || ! isset( $info['public_id'] ) || ! isset( $info['height'] ) )
    {
        WorkerLog( WORKER_WARNING, "Brand $brand ($brand_id) is missing data on `transcode.info.json`", 0, true, true, true );
        $result = QueryExecute( '77_brand_to_transcode_update.sql', $error, [ 'brand_id' => $brand_id ] );
        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - 77_brand_to_transcode_update.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
        InvalidateCache( 'brands' );
        return false;
        /*--- EXIT POINT ---*/
    }

    $action =    $info['action'];
    $public_id = $info['public_id'];
    $height =    $info['height'];


    // If not adding, clean

    if( $action !== 'add' )
    {
        foreach( $price_list as &$item )
        {
            $item['lngh'] = 0;
        } unset( $item );
    }


    // Get document id and its path

    $error = '';
    $result = QueryExecute( '78_brand_to_transcode_document.sql', $error, [ 'public_id' => $public_id ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 78_brand_to_transcode_document.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $document_id = $result[0]['document_id'];
    $description = $result[0]['description'];
    $pagesCount  = $result[0]['pages_count'];

    $pdfff = PathToPdfff( $document_id );


    // Get codes

    $codes = [];

    for( $pageIdx = 0; $pageIdx < $pagesCount; $pageIdx++ )
    {
        WorkerAlive();

        WorkerLog( WORKER_INFO, "Transcoder: parsing page " . ( $pageIdx + 1 ) . ":$pagesCount", $document_id, false, false, 1 );

        $text = PdfffText( $pdfff, $pageIdx );
        foreach( $text as $row )
        {
            if( $row['h'] !== $height )
            {
                continue;
            }

            $word = '';
            $chars = $row['chars'];
            foreach( $chars as $char )
            {
                $c = $char['c'];
                if( $c <= 32 || $c > 127 ) break;
                $word .= chr( $c );
            }

            if( strlen( $word ) > 2 ) // let code be at least 3 characters
            {
                $newcode = " " . strtolower( $word ); // code with a leading space

                $found = false;
                foreach( $codes as $c )
                {
                    if( $c === $newcode )
                    {
                        $found = true;
                        break;
                    }
                }
                if( ! $found )
                {
                    $codes[] = $newcode;
                }
            }
        }
    }


    // Sort codes by length DESC

    usort( $codes, function($a, $b) { return strlen($b) <=> strlen($a); } );

    file_put_contents( $brand_dir . "codes.txt", implode( "\n", $codes ) );


    // Match items

    $i = 1;
    $n = count( $price_list );
    foreach( $price_list as &$item )
    {
        WorkerLog( WORKER_INFO, "Transcoder: parsing item $i:$n", $document_id, false, false, 1 );
        if( $item['lngh'] === 0 )
        {
            foreach( $codes as $code )
            {
                if( strpos( $item[ 'srch' ], $code ) === 0 ) // both `srch` and $code have a leading space
                {
                    $item['lngh'] = strlen( $code ) - 1; // the leading space is not considered in `lngh`
                    break;
                }
            }
        }
        $i++;
    } unset( $item );


    // Save tab-separated price list for utility (note that this discards data type)

    ArrayToFile( $brand_dir . "pricelist.txt", $price_list );


    // JS file

    $price_list_js = $preamble . 'products = ' . json_encode( $price_list, JSON_UNESCAPED_SLASHES ) . $postabmle;

    file_put_contents( $price_list_path, $price_list_js );


    // Update the db

    $error = '';
    $result = QueryExecute( '77_brand_to_transcode_update.sql', $error, [ 'brand_id' => $brand_id ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 77_brand_to_transcode_update.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'brands' );


    // Log again

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Transcoding price list codes on brand $brand ($brand_id): $milliseconds ms", 0, false, false, 1 );


    // Rest

    sleep(3);


    // Log the db

    WorkerLog( WORKER_INFO, "Transcoded price list codes of $brand ($brand_id) from document $description mode: $action h=$height", $document_id, true, false, false );


    // Return the brand id to sync slave to master

    return $brand_id;
}
