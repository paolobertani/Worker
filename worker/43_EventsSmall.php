<?php

/*
 *
 *
 *  Manage Events Small
 *
 *
 */

function EventsSmall()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Manage Events Small table...", 0, false, false, 1 );

    $now = time();
    $timevalue_one_month_ago = strtotime( date( 'Y-m-d', $now - ( 3600 * 24 * 30 ) ) . ' 00:00:00' );

    $error = '';
    $result = QueryExecute( '49_es_cut_table.sql', $error, [ 'timevalue_one_month_ago' => $timevalue_one_month_ago ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 49_es_cut_table.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'events_small' );

    $error = '';
    $users = QueryExecute( '50_es_get_users.sql', $error, [] );

    if( $users === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 50_es_get_users.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $error = '';
    $stats = QueryExecute( '51_es_get_stats.sql', $error, [] );

    if( $stats === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 51_es_get_stats.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    foreach( $users as $user )
    {
        $l30dvw = 0;
        $l30ddl = 0;
        $l30dua = 0;
        $l30dip = 0;
        foreach( $stats as $stat )
        {
            if( $user['id'] == $stat['user_id'] )
            {
                $l30dvw = empty( $stat['views']    ) ? 0 : intval($stat['views']);
                $l30ddl = empty( $stat['download'] ) ? 0 : intval($stat['download']);
                $l30dua = empty( $stat['uacnt']    ) ? 0 : intval($stat['uacnt']);
                $l30dip = empty( $stat['ipcnt']    ) ? 0 : intval($stat['ipcnt']);
            }
        }

        $error = '';
        $result = QueryExecute( '52_es_update_user.sql', $error,
        [
            'user_id'=> $user['id'],
            'l30dvw' => $l30dvw,
            'l30ddl' => $l30ddl,
            'l30dua' => $l30dua,
            'l30dip' => $l30dip
        ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - 52_es_update_user.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
    }

    if( count( $users ) > 0 )
    {
        InvalidateCache( 'users' );
    }

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Manage Events Small table: $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}
