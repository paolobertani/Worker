<?php

//
//
// Delete expired sessions
//
//

function ExpiredSessionsDelete()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Delete expired sessions...", 0, false, false, 1 );

    $error = '';
    $result = QueryExecute( '60_delete_expired_sessions.sql', $error, [] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 60_delete_expired_sessions.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'sessions' );

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Delete expired sessions: $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}
