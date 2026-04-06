<?php

/*
 *
 *
 *  Delete bot events
 *
 *
 */

function DeleteBotEvents()
{
    $milliseconds = Milliseconds();
    $error = '';
    $query = "20_delete_bot_generated_events.sql";

    WorkerLog( WORKER_INFO, "Deleting bot generated event records...", 0, false, false, 1 );

    $result = QueryExecute( $query, $error );

    if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }

    InvalidateCache( 'events' );

    $milliseconds = Milliseconds( $milliseconds );

    WorkerLog( WORKER_INFO, "Deleting bot generated event records: $result deleted in $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep( 3 );
}
