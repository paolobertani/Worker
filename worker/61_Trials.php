<?php

//
//
// Trials
//
//

function Trials()
{
    TrialsExpired();
}



//
// TrialsExpired
//

function TrialsExpired()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Suspending expired trials whitout a subscription...", 0, false, false, 1 );

    // Users in trial period are moved to group 100 [SUBSCRIPTION INACTIVE] when the trial is over


    $count = 0;

    while( true )
    {
        $error = '';
        $result = QueryExecute( 'C1_trials_expired.sql', $error, [ 'NEXI_EMPTY_GROUP_ID' => NEXI_EMPTY_GROUP_ID ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - C1_trials_expired.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }

        if( count( $result ) === 0 )
        {
            break;
        }

        // the user made a trial, but then subscribed?

        $user_id = $result[ 0 ][ 'user_id' ];

        $error = '';
        $result = QueryExecute( 'C2_trials_user_is_subscribed.sql', $error, [ 'user_id' => $user_id ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - C2_trials_user_is_subscribed.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }

        if( count( $result ) === 1 )
        {
            break;
        }

        // no... suspend it

        $error = '';
        $result = QueryExecute( 'C3_trials_user_suspend.sql', $error, [ 'id' => $user_id, 'NEXI_EMPTY_GROUP_ID' => NEXI_EMPTY_GROUP_ID ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - C3_trials_user_suspend.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }

        if( $result == '1' ) // Affected rows
        {
            $count++;
        }
    }

    if( $count > 0 )
    {
        InvalidateCache( 'users' );
    }

    $logdb = $count > 0 ? true : false;
    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Suspended $count user(s) with Trial expired: $milliseconds ms", 0, $logdb, false, 1 );
    WorkerAlive();
    sleep(3);
}

