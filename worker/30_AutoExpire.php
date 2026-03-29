<?php

//
//
// Auto exire documents after a given amount of years
//
//

function AutoExpire()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Autoexpire documents with release date before " . WORKER_AUTOEXPIRE_YEARS_OLD . " years ago...", 0, false, false, 1 );


    // Select the date

    $now = date('Y-m');
    $old_release	 = AutoExpireAddYearsToDate( $now, -WORKER_AUTOEXPIRE_YEARS_OLD );
    $ignored_from	 = AutoExpireAddYearsToDate( $now, -WORKER_AUTOEXPIRE_YEARS_IGN );
	
	$now = date('Y-m-d h:i:s');
    $uploaded_before = AutoExpireAddYearsToDate( $now, -WORKER_AUTOEXPIRE_YEARS_UPL );


    // Select the docs

    $error = '';
    $result = QueryExecute( 'A8_documents_to_autoexpire.sql', $error, [ 'old_release' => $old_release, 'ignored_from' => $ignored_from, 'uploaded_before' => $uploaded_before ] );

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
        WorkerLog( WORKER_INFO, "Setting EXPIRE date to $n documents released before [$old_release], never read after [$ignored_from]", 0 );
    }

    foreach( $result as &$document )
    {
        $document[ 'expire' ] = AutoExpireAddYearsToDate( $document[ 'release' ], WORKER_AUTOEXPIRE_YEARS_ADD );
    } unset( $document );


    // Update

    foreach( $result as $document )
    {
        $error = '';
        $result = QueryExecute( 'A9_documents_to_ae_update.sql', $error, [ 'id' => $document[ 'id' ], 'expire' => $document[ 'expire' ] ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - A9_documents_to_ae_update.sql: query failed - Error: $error", 0, true, true, true );
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
    WorkerLog( WORKER_INFO, "Autoexpire documents with release date before " . WORKER_AUTOEXPIRE_YEARS_OLD . " years ago: $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);

}

function AutoExpireAddYearsToDate( $date, $years )
{
    $date = explode( "-", $date );
    $date[ 0 ] += $years;
    $date = implode( "-", $date );
    return $date;
}



