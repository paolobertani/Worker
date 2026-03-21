<?php

//
//
// Delete cookies older than 12 months
//
//

function ExpiredCookiesDelete()
{
    $milliseconds = Milliseconds();
    $error = '';
    $query = '62_delete_expired_cookies.sql';

    WorkerLog( WORKER_INFO, "Delete expired cookies...", 0, false, false, 1 );

    $result = QueryExecute( $query, $error, [] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Delete expired cookies: $result deleted in $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}
