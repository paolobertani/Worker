<?php

/*
 *
 *
 *  Trim searches_per_brand table
 *
 *
 */

function TrimSearchesPerBrand()
{
    $milliseconds = Milliseconds();
    $error = '';
    $query = '66_trim_searches_per_brand.sql';
    $when = ( intval( date( 'Y' ) ) - 3 ) . '-01';

    WorkerLog( WORKER_INFO, "Trim searches_per_brand table...", 0, false, false, 1 );

    $result = QueryExecute( $query, $error, [ 'when' => $when ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'searches_per_brand' );

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Trim searches_per_brand table: $result deleted in $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}
