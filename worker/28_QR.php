<?php

/*
 *
 *
 *  QR codes producing links and injecting into pdfff
 *
 *
 */



function QRTagPdfff( $document_id )
{
    $links = PdfffLinks( PathToPdfff( $document_id ) );

    $links = json_decode( $links, true );

    $modified = false;

    $pagesCount = count( $links );

    $qr_unique = [];


    // pdfff file structure check: There MUST be a links item for every page that contains links

    for( $pageIdx = 0; $pageIdx < $pagesCount; $pageIdx++ )
    {
        if( $links[ $pageIdx ]['page'] != $pageIdx )
        {
            WorkerLog( WORKER_ERROR, "FATAL - QRTagPdfff - pdfff file structure: There MUST be a links item for every page", $document_id, true, true, true );
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
            if( $links[ $pageIdx ]['targets'][ $targetIdx ]['page'] == -3 ) // qr code link tags have `page` value -3
            {
                array_splice( $links[ $pageIdx ]['targets'], $targetIdx, 1 );
                $targetsCount--;
                $targetIdx--;
                $modified = true;
            }
        }
    }


    // Detect QR code links and apply tags

    $path = PathToPdf( $document_id );

    for( $pageIdx = 0; $pageIdx < $pagesCount; $pageIdx++ )
    {
        $json = ImgQR( $document_id, $pageIdx );

        $qr_links = json_decode( $json, true );

        if( $qr_links === null )
        {
            WorkerLog( WORKER_ERROR, "FATAL - imgqr failed returning non-JSON data: " . substr( $json, 0, 20), $document_id, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }

        foreach( $qr_links as $link )
        {
            if( substr( $link['message'], 0, 8 ) === 'https://' || substr( $link['message'], 0, 7 ) === 'http://' || substr( $link['message'], 0, 7 ) === 'mailto:' || substr( $link['message'], 0, 4 ) === 'tel:' )
            {
                $modified = true;

                $qr_unique[] = $link['message'];

                $links[$pageIdx]['targets'][] = [
                    'url'       => $link['message'],
                    'page'      => -3,
                    'left'      => $link['x'],
                    'top'       => $link['y'],
                    'width'     => $link['w'],
                    'height'    => $link['h']
                ];
            }
        }

        WorkerAlive();
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

    $qr_unique = array_unique( $qr_unique );
    $qr_count = count( $qr_unique );

    return $qr_count;
}



