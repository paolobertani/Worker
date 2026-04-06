<?php

/*
 *
 *
 *  Set documents to not to be cached when they are expired long ago
 *
 *
 */

function AutoUncache()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Set documents to not to be cached with expire date " . WORKER_DONT_CACHE_YEARS_OLD . " years ago...", 0, false, false, 1 );


    // Select the date

    $now = date('Y-m');
    $old_expire = AutoExpireAddYearsToDate( $now, -WORKER_DONT_CACHE_YEARS_OLD ); // Use `AutoExpire` support function
    $ignored_from=AutoExpireAddYearsToDate( $now, -WORKER_DONT_CACHE_YEARS_IGN );

    // Select the docs

    $error = '';
    $result = QueryExecute( 'B1_documents_to_uncache.sql', $error, [ 'old_expire' => $old_expire, 'ignored_from' => $ignored_from ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - A8_documents_to_autoexpire.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }


    // Set expire date

    $n = count( $result );

    if( $n > 0 )
    {
        WorkerLog( WORKER_INFO, "Setting DONT_CACHE = 1 to $n documents with EXPIRE before [$old_expire] don't read from [$ignored_from]", 0 );
    }


    // Update

    foreach( $result as $document )
    {
        $error = '';
        $result = QueryExecute( 'B2_documents_to_uc_update.sql', $error, [ 'id' => $document[ 'id' ] ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - B2_documents_to_uc_update.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
    }

    if( $n > 0 )
    {
        InvalidateCache( 'documents' );
    }


    // Done

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Set documents to not to be cached with expire date " . WORKER_DONT_CACHE_YEARS_OLD . " years ago: $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);

}



