<?php

/*
 *
 *
 *  Idrolab pdfff tagging
 *
 *
 */



/*
 *
 *  Documents' Idrolab status
 *
 */

define( 'IDROLAB_NOT_TAGGED',    0 );
define( 'IDROLAB_TAGGED',        1 );
define( 'IDROLAB_HAS_NO_TAGS',   2 );
define( 'IDROLAB_HAS_OWN_LINKS', 4 );



function IdrolabTagPdfff( $document_id, $productlist )
{
    $links = PdfffLinks( PathToPdfff( $document_id ) );

    $links = json_decode( $links, true );

    $modified = false;

    $pagesCount = count( $links );


    // pdfff file structure check: There MUST be a links item for every page

    for( $pageIdx = 0; $pageIdx < $pagesCount; $pageIdx++ )
    {
        if( $links[ $pageIdx ]['page'] != $pageIdx )
        {
            WorkerLog( WORKER_ERROR, "FATAL - IdrolabTagPdfff - pdfff file structure: There MUST be a links item for every page", $document_id, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
    }


    // Remove existing tags if any

    for( $pageIdx = 0; $pageIdx < $pagesCount; $pageIdx++ )
    {
        $targetsCount = count( $links[ $pageIdx ]['targets'] );
        for( $targetIdx = 0; $targetIdx < $targetsCount; $targetIdx++ )
        {
            if( $links[ $pageIdx ]['targets'][ $targetIdx ]['page'] == -2 ) // idrolab tags have `page` value -2
            {
                array_splice( $links[ $pageIdx ]['targets'], $targetIdx, 1 );
                $targetsCount--;
                $targetIdx--;
                $modified = true;
            }
        }
    }

    $status = IDROLAB_NOT_TAGGED;


    // Check if the price list has already its own tags/links

    $ownLinksCount = 0;
    for( $pageIdx = 0; $pageIdx < $pagesCount; $pageIdx++ )
    {
        $ownLinksCount += count( $links[ $pageIdx ]['targets'] );
    }


    // Prevent tagging if document has its own links

    if( $ownLinksCount > $pagesCount * 4 )
    {
        $status = IDROLAB_HAS_OWN_LINKS;
        $productlist = false;
    }


    // Apply tags

    if( $productlist !== false )
    {
        $tags = IdrolabGetTags( $document_id, $productlist, $pagesCount );

        $status = IDROLAB_HAS_NO_TAGS;

        for( $pageIdx = 0; $pageIdx < $pagesCount; $pageIdx++ )
        {
            foreach( $tags[$pageIdx] as $t )
            {
                $links[$pageIdx]['targets'][] = [
                    'url'       => $t['url'],
                    'page'      => -2,
                    'left'      => $t['l'] / 10,
                    'top'       => $t['t'] / 10,
                    'width'     => $t['w'] / 10,
                    'height'    => $t['h'] / 10
                ];

                $modified = true;
                $status = IDROLAB_TAGGED;
            }
        }
    }


    // Store links

    if( $modified )
    {
        $links = json_encode( $links );

        $linksFile = MASTER_STORAGE_DIR . "/tmp/links." . rand( 10000, 99999 ) . ".txt";
        file_put_contents( $linksFile, $links );

        PdfffSetLinks( PathToPdfff( $document_id ), $linksFile );

        unlink( $linksFile );
    }


    // Done

    return $status;
}



/*
 *  Tags is a regular array; every index corresponds to a page number
 *  every item is an array with all the links on that page
 *  in case there are no links on a given page the corresponding item is an empty array
 */

function IdrolabGetTags( $document_id, $productlist, $pagesCount )
{
    // Let a tag item is present for every page

    $tags = [];
    for( $pageIdx = 0; $pageIdx < $pagesCount; $pageIdx++ )
    {
        $tags[$pageIdx] = [];
    }

    // check for duplicates is performed

    $codes = [];

    $pdfidx = PathToPdfidx( $document_id );

    $productlist = explode( "\n", $productlist );
    foreach( $productlist as $p )
    {
        $p = explode( "\t", $p );
        if( count( $p ) >= 2 )
        {
            $code = (string)$p[0];
            $url = $p[1];

            if( strpos( $url, '?' ) === false )
            {
                $url = "$url?from=pinaxo";
            }

            if( in_array( $code, $codes, true ) )
            {
                continue;
            }
            $codes[] = $code;

            $code = str_replace( ' ', '', $code ); // remove spaces to avoid multi-word search

            // = exact match: mathches the exact word; after the match the row either ends or has
            //                spaces or orther "ignore" characters before the next word

            $results = PdfidxFind( $pdfidx, "=$code" );

            $results = json_decode( $results, true );
            foreach( $results as $r )
            {
                $r['url'] = $url;
                $tags[$r['p']][] = $r;
            }
        }
        WorkerAlive();
    }

    return $tags;
}
