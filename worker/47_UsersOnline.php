<?php

//
//
// Populate Users Online
//
//

function UsersOnline()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Update count of users online...", 0, false, false, 1 );

    $error = '';
    $result = QueryExecute( '70_get_users_online.sql', $error, [] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 70_get_users_online.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $users = 0;
    $public = 0;
    $total = 0;

    foreach( $result as $r )
    {
        if( $r['public'] == 1 )
        {
            $public++;
        }
        else
        {
            $users++;
        }
        $total++;
    }

    $now = time();
    $min = intval( date( 'i', $now ) );
    $min = intdiv( $min, 5 ) * 5;
    $min = $min < 10 ? "0$min" : "$min";
    $when = date( "Y-m-d H:$min:00", $now );

    $error = '';
    $result = QueryExecute( '71_get_users_online_row.sql', $error, [ 'when' => $when ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 71_get_users_online_row.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( count( $result ) === 0 )
    {
        $error = '';
        $result = QueryExecute( '72_insert_users_online_row.sql', $error, [ 'users' => $users, 'public' => $public, 'total' => $total, 'when' => $when ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - 72_insert_users_online_row.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
    }
    else
    {
        $error = '';
        $result = QueryExecute( '73_update_users_online_row.sql', $error, [ 'users' => $users, 'public' => $public, 'total' => $total, 'when' => $when ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - 73_update_users_online_row.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
    }

    InvalidateCache( 'users_online' );

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Update count of users online: $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}
