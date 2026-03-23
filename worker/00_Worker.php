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

    $lastCacheTime = 0;
    $lastExpiredNotes = '';



    //
    // Idle character
    //

    $idlec = [ "-", "\\", "|", "/" ];
    $idlen = 0;


    //
    // Load persisted scheduling state
    //

    WorkerTasksStateLoad();



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

        WorkerTaskRun( 'hd.updates_mailing' );



        //
        // Statistics
        //

        WorkerTaskRun( 'hd.stats_searches' );



        //
        // QR Code Blog Page Table
        //

        WorkerTaskRun( 'hd.qr_table' );





        // ------------------





        if( ! HEAVY_DUTY )
        {

            //
            // Keep drives spinning
            //

            WorkerTaskRun( 'light.keep_drives_spinning' );



            //
            // Cash-in expired subscriptions
            //

            // Email activity may occur here

            WorkerTaskRun( 'light.subscriptions' );



            //
            // Suspend users in expired trials
            //

            WorkerTaskRun( 'light.trials' );



            //
            // Delete bot generated records from events table
            //

            WorkerTaskRun( 'light.delete_bot_events' );



            //
            // Backup databases
            //

            WorkerTaskRun( 'light.backup_databases' );



            //
            // Rotate log
            //

            WorkerTaskRun( 'light.log_rotate' );



            //
            // Generate Idrolab Stats
            //

            WorkerTaskRun( 'light.idrolab_stats' );



            //
            // Live Action
            //

            WorkerTaskRun( 'light.live_action' );



            //
            // Events Small
            //

            WorkerTaskRun( 'light.events_small' );



            //
            // Purge Sent Documents table
            //

            WorkerTaskRun( 'light.purge_sent_documents' );



            //
            // Delete expired sessions
            //

            WorkerTaskRun( 'light.expired_sessions_delete' );



            //
            // Delete cookies older than 12 months
            //

            WorkerTaskRun( 'light.expired_cookies_delete' );



            //
            // Trim usage table
            //

            WorkerTaskRun( 'light.trim_usage' );



            //
            // Trim usage_per_document table
            //

            WorkerTaskRun( 'light.trim_usage_per_document' );



            //
            // Trim usage_per_user table
            //

            WorkerTaskRun( 'light.trim_usage_per_user' );



            //
            // Trim searches_per_brand table
            //

            WorkerTaskRun( 'light.trim_searches_per_brand' );



            //
            // Rebuild brands per category
            //

            WorkerTaskRun( 'light.rebuild_brands_per_category' );



            //
            // Populate users online count table
            //

            WorkerTaskRun( 'light.users_online' );



            //
            // Load Excel price list
            //

            // Activity on brands

            WorkerTaskRun( 'light.manage_pricelist' );



            //
            // Transcode product codes to match with the codes on the PDFs
            //

            // Activity on brands

            WorkerTaskRun( 'light.manage_transcode' );



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

            WorkerTaskRun( 'light.check_php_fpm' );


            //
            // Check www.pinaxo.com cert is not expiring soon
            //

            WorkerTaskRun( 'light.check_cert' );


            //
            // Set EXPIRE date of documents with very old RELEASE date and not read
            //

            WorkerTaskRun( 'light.auto_expire' );


            //
            // Set documents to not to be cached when they are very old and not read
            //

            WorkerTaskRun( 'light.auto_uncache' );


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
