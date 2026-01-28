<?php

//
//
// Worker
//
//



//
// Excute in immediate mode (maybe)
//

function WorkerRunWithArgs()
{
    // Check for `-cmd`

    $cmd = ArgumentGet( 'cmd', ARGUMENT_OPTIONAL );
    if( $cmd !== false )
    {
        $id = ArgumentGet( 'id' );
        WorkerExecuteCmd( $cmd, $id );
        return true;
        /*--- EXIT POINT ---*/
    }


    // Check for `-sync`

    $id = ArgumentGet( 'sync', ARGUMENT_OPTIONAL );
    if( $id !== false )
    {
        WorkerExecuteSync( $id );
        return true;
        /*--- EXIT POINT ---*/
    }


    // no immediate command, let worker start in batch mode

    return false;
}



//
// Run tasks
//

function WorkerRun()
{
    //
    // Time each operation did execute the last time
    //

    $lastTouchTime = 0;
    $lastCacheTime = 0;
    $lastDeleteBotsTime = 0;
    $lastBackupDatabasesTime = 0;
    $lastLogRotationTime = 0;
    $lastIdrolabStats = 0;
    $lastLiveAction = 0;
    $lastEventsSmall = 0;
    $lastPurgeSentDocuments = 0;
    $lastExpiredSessionsDelete = 0;
    $lastRebuildBrandsPerCategory = 0;
    $lastUsersOnline = 0;
    $lastXlsLoad = 0;
    $lastTranscode = 0;
    $lastExpiredNotes = '';
    $lastUpdatesMailing = 0;
    $lastStats = 0;
    $lastQRTable = 0;
    $lastCheckPhpFpm = 0;
    $lastSubscriptions = 0;
    $lastTrials = 0;
    $lastCertCheck = 0;
    $lastAutoExpire = 0;
    $lastAutoUncache = 0;



    //
    // Idle character
    //

    $idlec = [ "-", "\\", "|", "/" ];
    $idlen = 0;



    //
    // Main loop
    //

    while( true )
    {
        //
        // Memory check
        //

        $mb = intdiv( memory_get_usage(), 1000 * 1000 );
        if( $mb > WORKER_MEMORY_LIMIT_MB )
        {
            WorkerLog( WORKER_NOTICE, "Exceeded memory limit with $mb MB used, issued a restart.", 0, true, true, true );
            Restart();
            /*--- QUIT (RESTART) POINT ---*/
        }

        //
        // Library version check
        //

        if( ExecShouldRestart( $vers ) )
        {
            WorkerLog( WORKER_NOTICE, "Detected new library version: $vers; issued a restart", 0, true, true, true );
            Restart();
            /*--- QUIT (RESTART) POINT ---*/
        }

        //
        // Cache operations - HEAVY DUTY
        //

        if( HEAVY_DUTY && time() - $lastCacheTime > WORKER_INTERVAL_CACHE )
        {
            // Enought disk space?

            if( ! EnoughtDiskSpace( $availableOnMaster, $availableOnSlave ) )
            {
                WorkerLog( WORKER_ERROR, "Running out of disk space - $availableOnMaster GB remaining on MASTER VOLUME " . ( $availableOnSlave === null ? "" : " - $availableOnSlave GB remaining on SLAVE VOLUME" ) . " - Terminating.", 0, true, true, true );
                WorkerQuitNow();
                /*--- QUIT POINT ---*/
            }


            // Record ids of document where operations occurr

            $document_id = [];


            // Run Tasks:

            //
            // each "Run" task returns...
            // `false`: no documents fall in that category
            // `true`: found one document that falls in that category but no ops took place
            // `document_id`: ops took place and affected the document with returned id
            //

            $document_id[] = RunDocumentAutolocked();
            $document_id[] = RunDocumentWithCommand();
            $document_id[] = RunDocumentToFixMD5();
            $document_id[] = RunDocumentToCacheV2();
            $document_id[] = RunDocumentToCover();
            $document_id[] = RunDocumentToColor();
            $document_id[] = RunDocumentToQR();
            $document_id[] = RunDocumentToMeta();
            $document_id[] = RunDocumentToUncacheV2();
            $document_id[] = RunDocumentToRemove();
            $document_id[] = RunSentDocumentToPdfff(); // always return `true` or `false`
            $document_id[] = RunDocumentToIdrolabTag( true ); // apply
            $document_id[] = RunDocumentToIdrolabTag( false ); // remove



            // Sync the affected document(s) on the slave volume
            // if it is present - checked into `SlaveSync()`

            foreach( $document_id as $id )
            {
                if( $id !== true && $id !== false )
                {
                    SlaveSync( $id );
                }
            }


            // Should pause cache operations?

            $sleep = true;
            foreach( $document_id as $id )
            {
                if( $id !== false )
                {
                    $sleep = false;
                    break;
                }
            }


            if( $sleep )
            {
                $lastCacheTime = time();
            }
        }



        //
        // Updates mailing
        //

        if( HEAVY_DUTY && time() - $lastUpdatesMailing > WORKER_INTERVAL_UPDATES_MAILING )
        {
            UpdatesNotify();
            UpdatesSend();
        }



        //
        // Statistics
        //

        if( HEAVY_DUTY && time() - $lastStats > WORKER_INTERVAL_STATS && intval(date('G')) >= 20 )
        {
            StatsSearchesBuild();
        }



        //
        // QR Code Blog Page Table
        //

        if( HEAVY_DUTY && time() - $lastQRTable > WORKER_INTERVAL_QRTABLE )
        {
            UpdateQrCountTablePage();
        }





        // ------------------





        if( ! HEAVY_DUTY )
        {

            //
            // Keep drives spinning
            //

            if( time() - $lastTouchTime > WORKER_INTERVAL_TOUCH )
            {
                KeepDrivesSpinning();
                $lastTouchTime = time();
            }



            //
            // Cash-in expired subscriptions
            //

            // Email activity may occur here

            if( is_dir( ROOT . "/CASHIN" ) || ( time() - $lastSubscriptions > WORKER_INTERVAL_SUBSCRIPTIONS && intval(date('G')) >= WORKER_CASHIN_START_AT && intval(date('G')) <= WORKER_CASHIN_END_AT ) )
            {
				if( is_dir( ROOT . "/CASHIN" ) ) { exec( "mv " . ROOT . "/CASHIN" . " " . ROOT . "/CASHIN-EXECUTED" ); }
                Subscriptions();
                $lastSubscriptions = time();
                WorkerAlive();
            }



            //
            // Suspend users in expired trials
            //

            if( time() - $lastTrials > WORKER_INTERVAL_TRIALS )
            {
                Trials();
                $lastTrials = time();
                WorkerAlive();
            }



            //
            // Delete bot generated records from events table
            //

            if( time() - $lastDeleteBotsTime > WORKER_INTERVAL_DELETEBOTS )
            {
                DeleteBotEvents();
                $lastDeleteBotsTime = time();
                WorkerAlive();
            }



            //
            // Backup databases
            //

            if( time() - $lastBackupDatabasesTime > WORKER_INTERVAL_DATABASES )
            {
                BackupDatabases();
                $lastBackupDatabasesTime = time();
                WorkerAlive();
            }



            //
            // Rotate log
            //

            if( time() - $lastLogRotationTime > WORKER_INTERVAL_LOGROTATE )
            {
                LogRotate();
                $lastLogRotationTime = time();
                WorkerAlive();
            }



            //
            // Generate Idrolab Stats
            //

            if( time() - $lastIdrolabStats > WORKER_INTERVAL_IDROLABSTATS )
            {
                IdrolabDoStats();
                $lastIdrolabStats = time();
                WorkerAlive();
            }



            //
            // Live Action
            //

            if( time() - $lastLiveAction > WORKER_INTERVAL_LIVEACTION )
            {
                LiveAction();
                $lastLiveAction = time();
                WorkerAlive();
            }



            //
            // Events Small
            //

            if( time() - $lastEventsSmall > WORKER_INTERVAL_EVENTSSMALL )
            {
                EventsSmall();
                $lastEventsSmall = time();
                WorkerAlive();
            }



            //
            // Purge Sent Documents table
            //

            if( time() - $lastPurgeSentDocuments > WORKER_INTERVAL_PURGESDOCS )
            {
                PurgeSentDocuments();
                $lastPurgeSentDocuments = time();
                WorkerAlive();
            }



            //
            // Delete expired sessions
            //

            if( time() - $lastExpiredSessionsDelete > WORKER_INTERVAL_DEL_EXPS )
            {
                ExpiredSessionsDelete();
                $lastExpiredSessionsDelete = time();
                WorkerAlive();
            }



            //
            // Rebuild brands per category
            //

            if( time() - $lastRebuildBrandsPerCategory > WORKER_INTERVAL_REBUILD_BPC )
            {
                BrandsPerCategoryRebuild();
                $lastRebuildBrandsPerCategory = time();
                WorkerAlive();
            }



            //
            // Populate users online count table
            //

            if( time() - $lastUsersOnline > WORKER_INTERVAL_USERS_ONLINE )
            {
                UsersOnline();
                $lastUsersOnline = time();
                WorkerAlive();
            }



            //
            // Load Excel price list
            //

            // Activity on brands

            if( time() - $lastXlsLoad > WORKER_INTERVAL_XLS )
            {
                $brand_op = ManagePricelist();
                if( $brand_op !== false )
                {
                    SlaveSyncBrands( [ $brand_op ] );
                }
                $lastXlsLoad = time();
                WorkerAlive();
            }



            //
            // Transcode product codes to match with the codes on the PDFs
            //

            // Activity on brands

            if( time() - $lastTranscode > WORKER_INTERVAL_TRANSCODE )
            {
                $brand_op = ManageTranscode();
                if( $brand_op !== false )
                {
                    SlaveSyncBrands( [ $brand_op ] );
                }
                $lastTranscode = time();
                WorkerAlive();
            }



            //
            // Send email notification for notes on expired documents
            //

            // Email activity may occur here

            if( intval(date('G')) >= 7 && intval(date('G')) <= 9 && date( 'Y/m/d' ) !== $lastExpiredNotes )
            {
                $lastExpiredNotes = date( 'Y/m/d' );
                ExpiredNotes();
            }



            //
            // Check PHP-FPM Pinaxo Blog is not blocked
            //

            if( time() - $lastCheckPhpFpm > WORKER_INTERVAL_CHECK_PHP_FPM )
            {
                CheckPhpFpm();
                $lastCheckPhpFpm = time();
                WorkerAlive();
            }


            //
            // Check www.pinaxo.com cert is not expiring soon
            //

            if( time() - $lastCertCheck > WORKER_INTERVAL_CHECK_CERT )
            {
                CheckCert();
                $lastCertCheck = time();
                WorkerAlive();
            }


            //
            // Set EXPIRE date of documents with very old RELEASE date and not read
            //

            if( time() - $lastAutoExpire > WORKER_INTERVAL_AUTO_EXPIRE )
            {
                AutoExpire();
                $lastAutoExpire = time();
                WorkerAlive();
            }


            //
            // Set documents to not to be cached when they are very old and not read
            //

            if( time() - $lastAutoUncache > WORKER_INTERVAL_AUTO_UNCACHE )
            {
                AutoUncache();
                $lastAutoUncache = time();
                WorkerAlive();
            }


        }



        // -------------------------



        //
        // Stay idle
        //
		
		$usersOnlineCount = "";
		if( ! HEAVY_DUTY )
		{
		    $error = '';
		    $result = QueryExecute( '70_get_users_online_count.sql', $error, [] );
			$usersOnlineCount = $result === false ? "?" : $result[ 0 ][ 'users_online_count' ];
			$usersOnlineCount .= " users online";
		}

        $mem = memory_get_usage( true );
        $mem = intval( round( $mem / 1000000, 0 ) );

        EchoCR( "Memory usage: $mem MB - Worker idle " . $idlec[ $idlen % count( $idlec ) ] . " $usersOnlineCount " );
        $idlen++;
        sleep( HEAVY_DUTY ? 2 : 1  );
        WorkerQuitMaybe();
        WorkerAlive();



        //
        //
        //
    }
}



//
// Check if should quit
//

function WorkerQuitMaybe()
{
    $quit = false;

    if( WORKER_SIGNALS )
    {
        $quit = SignalQuitReceived();
    }
    else
    {
        $quit = FileExists( ROOT . '/stop' );
        if( $quit )
        {
            unlink( ROOT . '/stop' );
        }
    }

    if( $quit )
    {
        WorkerLog( WORKER_INFO, 'Worker' . ( HEAVY_DUTY ? ' Heavy duty ' : ' ' ) . 'stopped', null, true, true, false );
        echo "\n---\nStopped.\n";
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



//
// Stop execution immediatedly
//

function WorkerQuitNow()
{
    exit(0);
}



//
// Return true if present time
// falls into passed interval
// in the form 'h:mm-h:mm'

function WorkerShouldPause( $when )
{
    $interval = explode( '-', $when );
    $from = explode( ":", $interval[ 0 ] );
    $to = explode( ":", $interval[ 1 ] );

    $from = $from[0] * 3600 + $from[1] * 60;
    $to = $to[0] * 3600 + $to[1] * 60 + 59;

    $now = time() - strtotime("today");

    if( $now >= $from && $now <= $to )
    {
        return true;
    }

    return false;
}



//
// Restart the worker
//

function Restart()
{
    ExecRestart( ROOT . "/worker.php", [ '-restart' ] );
    /*--- QUIT (RESTART) POINT ---*/
}


