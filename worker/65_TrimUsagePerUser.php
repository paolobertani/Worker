<?php

/*
 *
 *
 *  Trim usage_per_user table
 *
 *
 */

function TrimUsagePerUser()
{
    $milliseconds = Milliseconds();
    $error = '';
    $query = '65_trim_usage_per_user.sql';
    $when = ( intval( date( 'Y' ) ) - 2 ) . '-01';

    WorkerLog( WORKER_INFO, "Trim usage_per_user table...", 0, false, false, 1 );

    $result = QueryExecute( $query, $error, [ 'when' => $when ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'usage_per_user' );

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Trim usage_per_user table: $result deleted in $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}
