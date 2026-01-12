<?php

//
//
// Metadata generation
//
//



//
// Generate metadata
//

function MetaDataProduce( $document_id, &$meta )
{
    $meta = [];

    $path_to_pdf = PathToPdf( $document_id );
    if( ! FileExists( $path_to_pdf ) )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $path_to_pdfff = PathToPdfff( $document_id );
    if( ! FileExists( $path_to_pdfff ) )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $actualMD5 = Md5OfFile( $path_to_pdf );

    $path_to_meta = str_replace( '.pdfff', '.meta.json', $path_to_pdfff );
    if( FileExists( $path_to_meta ) )
    {
        RemoveFile( $path_to_meta, $document_id );
    }


    // Pages count

    $pages_count = (int)PdfSize( $path_to_pdfff, '', '', false, true );


    // Info on pdf

    $info = PdfInfo( $path_to_pdf );


    // Pdf outlines

    $outlines = PdfffOutlines( $path_to_pdfff );
    $labels_count = 0;
    $levels_count = 0;
    $labels_count = MetaCountOutlines( $outlines, $levels_count );
    $has_outlines = $labels_count > 10 ? 1 : 0;


    // Load text/rows

    $text = false;

    $path_to_rows = str_replace( '.pdfff', '.rows.txt', $path_to_pdfff );
    if( FileExists( $path_to_rows ) )
    {
        $text = file_get_contents( $path_to_rows );
        $text = explode( "\n", $text );
        $md5 = $text[0];
        unset( $text[0] );

        if( $md5 !== $actualMD5 )
        {
            $text = false;
            RemoveFile( $path_to_rows, $document_id );
        }
    }


    // Build rows/text

    if( $text === false )
    {
        $prev_page_num = -1;

        for( $page_num = 0; $page_num < $pages_count; $page_num++ )
        {
            WorkerAlive();

            $rows = PdfffText( $path_to_pdfff, $page_num );

            $prev_height = false;
            $prev_left   = false;
            $prev_top    = false;
            $prev_right  = false;

            $rows_count = 0;

            foreach( $rows as $row )
            {
                $height = (int) $row['h'];
                $left   = (int) $row['l'];
                $top    = (int) $row['t'];
                $right  = $left + (int) $row['w'];

                $chars = $row['chars'];
                $row_text = '';
                foreach( $chars as $char )
                {
                    $u = (int)$char['c'];
                    if( $u === 10 ) $u = 32;
                    $row_text .= chr( $u % 256 ) . chr( intdiv( $u, 256 ) );
                }

                $row_text = mb_convert_encoding( $row_text, 'UTF-8', 'UTF-16LE' );

                $row_text = trim( $row_text );

                $row_text = mb_strtolower( $row_text );

                while( strpos( $row_text, '  ' ) !== false ) { $row_text = str_replace( '  ', ' ', $row_text ); }

                if( $row_text !== '' )
                {
                    if( $height === $prev_height && $page_num === $prev_page_num && ( $left === $prev_left || ( $top === $prev_top && $left - $prev_right < $height * 2 ) ) )
                    {
                        $text .= " " . $row_text;
                    }
                    else
                    {
                        $pad_height = str_pad( $height, 7, '0', STR_PAD_LEFT );
                        $pad_page_num = str_pad( $page_num, 7, '0', STR_PAD_LEFT );
                        $text .= "\n$pad_page_num|$pad_height|$row_text";
                    }

                    $prev_height    = $height;
                    $prev_left      = $left;
                    $prev_top       = $top;
                    $prev_right     = $right;
                    $prev_page_num  = $page_num;
                }
            }
        }

        $text = trim( $text );

        $text = "$actualMD5\n$text";

        file_put_contents( $path_to_rows, $text );

        $text = explode( "\n", $text );
        unset( $text[0] ); // strip away the md5
    }


    // Identify key rows


    $rows = [];
    $num = 0;
    foreach( $text as $t )
    {
        $p = (int)substr( $t, 0, 7);
        $rows[] = [
            'num'   =>  $num,
            'sel'   =>  false,
            'page'  =>  $p,
            'height'=>  (int)substr( $t, 8, 7),
            'text'  =>  substr( $t, 16 )
        ];
    }

    ArrayRemoveDuplicates( $rows, 'text', function( $block ) { foreach( $block as &$b ) { $b['score'] = $b['height']; } unset( $b ); }, 'score' );

    ArraySortByKey( $rows, [ 'heightDESC', 'pageASC' ] );

    $i = 1;
    $n = min( $pages_count, intdiv( count( $rows ), 20 ) ); // how many to keep: min between pages count and rows count divided 20
    foreach( $rows as &$r )
    {
        if( strlen( $r['text'] ) >= 2 )
        {
            $r['sel'] = true;
            $i++;
            if( $i === $n ) break;
        }
    } unset( $r );

    ArraySortByKey( $rows, 'num' );

    $key_rows = [];

    foreach( $rows as $r )
    {
        if( $r['sel'] )
        {
            $key_rows[] = $r['text'];
        }
    }


    // Build meta and save

    $meta = [
        'md5' => $actualMD5,
        'pages_count' => $pages_count,
        'pdf_size' => GetFileSize( $path_to_pdf ),
        'pdf_modified' => $info['modified'],
        'pdf_created' => $info['created'],
        'has_outlines' => $has_outlines,
        'outlines_count' => $labels_count,
        'outlines_depth' => $levels_count,
        'outlines' => $outlines,
        'key_rows' => $key_rows
    ];


    file_put_contents( $path_to_meta, json_encode( $meta, JSON_PRETTY_PRINT ) );

    return true;
}



//
// Stats about outlines
//

function MetaCountOutlines( $outlines, &$max_level, $cur_level = 1 )
{
    $labels_count = 0;

    if( count( $outlines ) > 0 )
    {
        if( $cur_level >= $max_level )
        {
            $max_level = $cur_level;
        }
    }

    foreach( $outlines as $o )
    {
        $labels_count++;
        if( array_key_exists( 'c', $o ) )
        {
            $labels_count += MetaCountOutlines( $o['c'], $max_level, $cur_level + 1 );
        }
    }

    return $labels_count;
}
