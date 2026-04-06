<?php

/*
 *
 *
 *  Prevent the hard drive to stop
 *
 *
 */

function KeepDrivesSpinning()
{
    // Let the master volume disk keep spinning by touching a file

    $touchFile = MASTER_STORAGE_DIR . '/touch/spin';

    $output = Execute( [ '/usr/bin/touch', $touchFile ], $exitStatus );
    if( $exitStatus != 0 )
    {
        WorkerLog( WORKER_ERROR, "FATAL - KeepDrivesSpinning: failed to touch $touchFile - Exit Status: $exitStatus - Output: $output", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }


    // If slave volume is not present then operation is done

    if( ! SlaveIsPresent() )
    {
        return;

    }

    $touchFile = SLAVE_STORAGE_DIR . '/touch/spin';

    // Let the slave drive keep spinning too

    $output = Execute( [ '/usr/bin/touch', $touchFile ], $exitStatus );
    if( $exitStatus != 0 )
    {
        WorkerLog( WORKER_ERROR, "FATAL - KeepDrivesSpinning: failed to touch $touchFile - Exit Status: $exitStatus - Output: $output", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}
