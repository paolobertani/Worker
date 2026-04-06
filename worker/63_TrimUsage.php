<?php

/*
 *
 *
 *  Trim usage table
 *
 *
 */

function TrimUsage()
{
    $milliseconds = Milliseconds();
    $error = '';
    $query = '63_trim_usage.sql';
    $when = ( intval( date( 'Y' ) ) - 1 ) . '-01';

    WorkerLog( WORKER_INFO, "Trim usage table...", 0, false, false, 1 );

    $result = QueryExecute( $query, $error, [ 'when' => $when ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'usage' );

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Trim usage table: $result deleted in $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}
