<?php

//
//
// Backup databases
//
//


function BackupDatabases()
{
    if( BackupDatabasesShouldPause() )
    {
        return;
        /*--- EXIT POINT ---*/
    }

    $milliseconds = Milliseconds();

    WorkerLog( WORKER_INFO, "Perform backup of databases...", 0, false, false, 1 );

    BackupDatabasesArchiveLast();

    BackupDatabasesDump();

    BackupDatabasesRotate();

    BackupDatabasesSyncStorageVolumes();

    $milliseconds = Milliseconds( $milliseconds );

    WorkerLog( WORKER_INFO, "Perform backup of databases: $milliseconds ms", 0, false, false, 1 );
    sleep( 3 );
}



//
// Archive the last databases dump
//

function BackupDatabasesArchiveLast()
{
    // Root databases archive path

    $root = BDB_PATH;


    // Enumerate database dumps to archive, exit if no one is found

    $files = FilesInDirectory( $root );
    $databases = [];
    foreach( $files as $f )
    {
        if( strtolower( substr( $f, -4, 4 ) ) === '.sql' )
        {
            $databases[] = $f;
        }
    }
    if( count( $databases ) === 0 )
    {
        return;
        /*--- EXIT POINT ---*/
    }


    // Get dump date-time and build directory with date-time in the name

    $when = date( 'Y-m-d-H-i-s', filemtime( $root . '/' . $databases[ 0 ] ) );
    $path = "$root/archive/dbdump-$when";
    MakeDirectoryTree( $path );


    // Move dump files to directory

    foreach( $databases as $db )
    {
        $result = rename( "$root/$db", "$path/$db" );
        if( ! $result )
        {
            WorkerLog( WORKER_ERROR, "FATAL - BackupDatabasesArchiveLast: failed to move file - from: $root/$db - to: $path/$db", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
    }


    // ZIP directory

    $result = chdir( "$root/archive" );
    if( ! $result )
    {
        WorkerLog( WORKER_ERROR, "FATAL - BackupDatabasesArchiveLast - chdir failed: chdir($root/archive)", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
    $exitStatus = 0;
    $toolcall = [ "zip -rq", "$path.zip", "dbdump-$when" ];
    $output = Execute( $toolcall, $exitStatus );
    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - BackupDatabasesArchiveLast - zip failed: $output - command: $toolcall", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }


    // Delete directory

    $exitStatus = 0;
    $toolcall = [ "rm -rf", $path ];
    $output = Execute( $toolcall, $exitStatus );
    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - BackupDatabasesArchiveLast - rm failed: $output - command: $toolcall", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



//
// Dump all the listed databases
//

function BackupDatabasesDump()
{
    $excludes  = explode( ',', BDB_EXCLUDES );
    $root = BDB_PATH;

    $databaselist = QueryExecute( 'show databases', $error );
    if( $databaselist === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - `show databases`: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $databases = [];

    foreach( $databaselist as $entry )
    {
        $databases[] = $entry['Database'];
    }

    foreach( $databases as $db )
    {
        // exclude databases

        if( in_array( $db, $excludes ) )
        {
            continue;
        }

        $tf = [ true, false ];

        foreach( $tf as $structureOnly )
        {
            $outPath = $structureOnly ? "$root/$db.structure.sql" : "$root/$db.data.sql";

            // toolcall & main options

            $toolcall = [ '/usr/local/mysql/bin/mysqldump --opt --single-transaction' ];
            $toolcall[] = '--user=' . DB_USER;
            $toolcall[] = '--password=' . DB_PASS;
            $toolcall[] = '--host=' . DB_HOST;
            $toolcall[] = "--result-file=$outPath";

            if( $structureOnly )
            {
                $toolcall[] = "--no-data";
            }
            else
            {
                $toolcall[] = "--no-create-db";
                $toolcall[] = "--no-create-info";
            }

            // excluded tables for this database (only when exporting contents)

            if( ! $structureOnly )
            {
                foreach( $excludes as $exclude )
                {
                    $parts = explode( '::', $exclude );

                    if( $db === $parts[0] )
                    {
                        $table = $parts[1];
                        $toolcall[] = "--ignore-table=$db.$table";
                    }
                }
            }


            // add database name and execute

            $toolcall[] = $db;

            $exitStatus = 0;
            $output = Execute( $toolcall, $exitStatus );
            if( $exitStatus != 0 )
            {
                $toolcall = implode( ' ', $toolcall );
                WorkerLog( WORKER_ERROR, "FATAL - BackupDatabasesDump - mysqldump failed: $output - command: $toolcall", 0, true, true, true );
                WorkerQuitNow();
                /*--- QUIT POINT ---*/
            }
        }
    }
}



//
// Rotate the archive
//

// For each interval, all the backups
// that fall into the timespan are
// deleted except the oldest.
//
// Backups into the interval older
// than interval beginning + timespan
// are ignored.
//
// The interval's timespan is to be
// intended as the -minimum- timespan
// between backups into the interval.
//
// It is advisable to avoid backups
// at a timespan slighly smaller than
// the interval's timespan they enter
// to let the timespan between backups
// better match the interval's timespan
//
// Backups are scheduled every 1800 secs
// but actually they take place at a
// slightly slower pace due to delays;
// For this reason they properly enter
// the "every 30 min. in the first 4 hrs."
// main interval.

function BackupDatabasesRotate()
{
    // Intervals

    $h = 3600;
    $d = $h * 24;
    $w = $d * 7;
    $m = $w * 4;
    $intervals = [
        [ 'from' => 0, 'to' =>   1800, 'timespan' =>      5 ],   // every 5'' for the first half hour (allowed)
        [ 'from' => 0, 'to' => 4 * $h, 'timespan' =>   1800 ],   // every 30' for the first 4 hours  8
        [ 'from' => 0, 'to' =>24 * $h, 'timespan' => 1 * $h ],   // every 1h up to 24 hours          20
        [ 'from' => 0, 'to' => 9 * $d, 'timespan' => 1 * $d ],   // every 1d up to 9 days            8
        [ 'from' => 0, 'to' => 6 * $m, 'timespan' => 1 * $w ]    // every 1w up to 6 months          ~24
    ];
    $from = 0;
    for( $i = 0; $i < count( $intervals ); $i++ )
    {
        $intervals[ $i ]['from'] = $from;
        $from = $intervals[ $i ]['to'] + 1;
    }
    $maxage = $from;


    // Backups

    $now = time();
    $archivePath = BDB_PATH . '/archive';
    $files = FilesInDirectory( $archivePath );
    $backups = [];
    foreach( $files as $f )
    {
        $age = false;
        if( substr( $f, 0, 7 ) === 'dbdump-' && substr( $f, -4, 4 ) === '.zip' )
        {
            $datetime = DateTime::createFromFormat( 'Y-m-d-H-i-s', substr( $f, 7, 19 ) );
            if( $datetime !== false )
            {
                $valid = true;
                $age = $now - $datetime->getTimestamp();
            }
        }

        if( $age !== false )
        {
            $backups[] = [ 'path' => "$archivePath/$f", 'age' => $age, 'interval' => false, 'remove' => false ];
        }
        else
        {
            WorkerLog( WORKER_NOTICE, "BackupDatabasesRotate - extraneous file in archive - $f", 0, true, true, true );
        }
    }


    // Sort backups

    ArraySortByKey( $backups, 'age' );


    // Backups' interval

    for( $i = 0; $i < count( $backups ); $i++ )
    {
        for( $j = 0; $j < count( $intervals ); $j++ )
        {
            if( $backups[$i]['age'] >= $intervals[$j]['from'] && $backups[$i]['age'] <= $intervals[$j]['to'] )
            {
                $backups[$i]['interval'] = $j;
                break;
            }
        }
    }


    // Flag backups to remove

    for( $i = 0; $i < count( $backups ) - 1; $i++ )
    {
        if( $backups[$i]['interval'] !== false && $backups[ $i + 1 ]['interval'] !== false && $backups[$i]['interval'] === $backups[ $i + 1 ]['interval'] )
        {
            $idx = $backups[$i]['interval'];

            if( $backups[ $i + 1 ]['age'] <= $intervals[$idx]['from'] + $intervals[$idx]['timespan'] )
            {
                $backups[$i]['remove'] = true;
            }
        }
    }

    for( $i = 0; $i < count( $backups ); $i++ )
    {
        if( $backups[$i]['age'] >= $maxage )
        {
            $backups[$i]['remove'] = true;
        }
    }


    // Remove flagged backups

    foreach( $backups as $backup )
    {
        if( $backup['remove'] )
        {
            $exitStatus = 0;
            $toolcall = [ "rm", $backup['path'] ];
            $output = Execute( $toolcall, $exitStatus );
            if( $exitStatus != 0 )
            {
                $toolcall = implode( ' ', $toolcall );
                WorkerLog( WORKER_ERROR, "FATAL - BackupDatabasesRotate - rm failed: $output - command: $toolcall", 0, true, true, true );
                WorkerQuitNow();
                /*--- QUIT POINT ---*/
            }
        }
    }
}



//
// Return true if at present time
// the database dumps should be paused
// (normally during main backup)
//

function BackupDatabasesShouldPause()
{
    return WorkerShouldPause( BDB_PAUSE );
}



//
// Align the backups in the master and slave volumes
//

function BackupDatabasesSyncStorageVolumes()
{
    if( ! SlaveIsPresent() )
    {
        return;
        /*--- EXIT POINT ---*/
    }

    $source = BDB_PATH . '/';
    $target = BDB_PATH_SLAVE;

    $toolcall = [ '/usr/bin/rsync -a --delete', $source, $target ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "BackupDatabasesSyncStorageVolumes: failed with status: $exitStatus - $output - command: $toolcall", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



