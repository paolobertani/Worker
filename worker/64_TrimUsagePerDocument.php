<?php

/*
 *
 *
 *  Trim usage_per_document table
 *
 *
 */

function TrimUsagePerDocument()
{
    $milliseconds = Milliseconds();
    $error = '';
    $query = '64_trim_usage_per_document.sql';
    $when = ( intval( date( 'Y' ) ) - 3 ) . '-01';

    WorkerLog( WORKER_INFO, "Trim usage_per_document table...", 0, false, false, 1 );

    $result = QueryExecute( $query, $error, [ 'when' => $when ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'usage_per_document' );

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Trim usage_per_document table: $result deleted in $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}
