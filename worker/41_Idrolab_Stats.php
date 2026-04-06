<?php

/*
 *
 *
 *  Idrolab Statistics
 *
 *
 */

function IdrolabDoStats()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Idrolab stats...", 0, false, false, 1 );

    $error = '';
    $result = QueryExecute( '43_idrolab_stats.sql', $error, [ ] );
    if( $result === false )
    {
        EchoNL( "43_idrolab_stats.sql: query failed - Error: $error" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $result = $result[ 0 ];

    foreach( $result as &$r )
    {
        if( ctype_digit( $r ) )
        {
            $r = (int) $r;
        }
    } unset( $r );

    $result = json_encode( $result, JSON_PRETTY_PRINT );

    file_put_contents( PATH_TO_MISC . "idrolab_stats.json", $result );

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Idrolab stats: $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}