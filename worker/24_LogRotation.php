<?php

/*
 *
 *
 *  Log rotation
 *
 *
 */



/*
 *  LogRotate
 *
 *  Run log maintenance in the configured NGINX directories and report its duration.
 *
 *  edited by Samantha Allman (aka Codex GPT-6 Default)
 *
 */

function LogRotate()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Rotating logs...", 0, false, false, 1 );

    $dirs = LOG_DIRECTORIES;

    foreach( $dirs as $dir )
    {
        LogRotateDir( $dir );
    }

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Rotating logs: $milliseconds ms", 0, false, false, 1 );
    sleep(3);
}



/*
 *  LogRotateDir
 *
 *  Visit active log files recursively, excluding every archive history directory.
 *
 *  edited by Samantha Allman (aka Codex GPT-6 Default)
 *
 */

function LogRotateDir( $root )
{
    if( str_ends_with( rtrim( $root, '/' ), '.history' ) )
    {
        return;
    }

    PathAppendSlash( $root );

    $files = FilesInDirectory( $root );
    foreach( $files as $file )
    {
        LogRotateFile( "$root$file" );
    }

    $dirs = DirectoriesInDirectory( $root );
    foreach( $dirs as $dir )
    {
        LogRotateDir( "$root$dir" );
    }
}



/*
 *  LogRotateFile
 *
 *  Archive a log at the size threshold, truncate its existing inode, then prune its history.
 *  Writes between the end of the copy and truncation can be lost (copytruncate).
 *
 *  edited by Samantha Allman (aka Codex GPT-6 Default)
 *
 */

function LogRotateFile( $file )
{
    if( substr( $file, -4, 4 ) !== '.log' )
    {
        return;
        /*--- EXIT POINT ---*/
    }

    $historyDirectory = $file . '.history';

    if( ! is_dir( $historyDirectory ) && ! @mkdir( $historyDirectory, 0755 ) )
    {
        WorkerLog( WORKER_WARNING, 'Log rotation: unable to create history directory ' . $historyDirectory, 0, true, false, true );
        return;
    }

    if( GetFileSize( $file ) < LOG_SIZE )
    {
        return;
        /*--- EXIT POINT ---*/
    }

    $logHandle = @fopen( $file, 'r+b' );

    if( $logHandle === false )
    {
        WorkerLog( WORKER_WARNING, 'Log rotation: unable to open the active log ' . $file, 0, true, false, true );
        return;
    }

    $rotationTimestamp = ( new \DateTimeImmutable() )->format( 'Y-m-d_H-i-s.u' );
    $archivePath = $historyDirectory . '/' . basename( $file ) . '.' . $rotationTimestamp;
    $temporaryArchivePath = $archivePath . '.tmp';

    // copy() transfers the file without loading its full contents into PHP memory.

    if( ! @copy( $file, $temporaryArchivePath ) )
    {
        @unlink( $temporaryArchivePath );
        fclose( $logHandle );
        WorkerLog( WORKER_WARNING, 'Log rotation: unable to copy ' . $file . '; active log preserved', 0, true, false, true );
        return;
    }

    if( ! @chmod( $temporaryArchivePath, fileperms( $file ) & 0777 ) || ! @rename( $temporaryArchivePath, $archivePath ) )
    {
        @unlink( $temporaryArchivePath );
        fclose( $logHandle );
        WorkerLog( WORKER_WARNING, 'Log rotation: unable to finalize archive ' . $archivePath . '; active log preserved', 0, true, false, true );
        return;
    }

    $truncated = ftruncate( $logHandle, 0 );
    fclose( $logHandle );

    if( ! $truncated )
    {
        WorkerLog( WORKER_WARNING, 'Log rotation: unable to truncate ' . $file . '; archive retained at ' . $archivePath, 0, true, false, true );
        return;
    }

    PruneLogHistory( $file );
}



/*
 *  PruneLogHistory
 *
 *  Retain the newest timestamped archives for one log, leaving unrelated history files intact.
 *
 *  by Samantha Allman (aka Codex GPT-6 Default)
 *
 */

function PruneLogHistory( $file )
{
    $historyDirectory = $file . '.history';
    $archivePattern = '/^' . preg_quote( basename( $file ), '/' ) . '\.\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.\d{6}$/D';
    $archiveNames = [];

    foreach( FilesInDirectory( $historyDirectory ) as $historyFile )
    {
        if( preg_match( $archivePattern, $historyFile ) === 1 )
        {
            $archiveNames[] = $historyFile;
        }
    }

    rsort( $archiveNames, SORT_STRING );

    foreach( array_slice( $archiveNames, LOG_HISTORY_FILES_TO_KEEP ) as $archiveName )
    {
        $archivePath = $historyDirectory . '/' . $archiveName;

        if( ! @unlink( $archivePath ) )
        {
            WorkerLog( WORKER_WARNING, 'Log rotation: unable to remove old archive ' . $archivePath, 0, true, false, true );
        }
    }
}



/*
 *  RotatePhpFpmErrorLog
 *
 *  Apply the shared copytruncate rotation to the PHP-FPM error log when it exists.
 *  The active inode stays unchanged, so no reopening signal or marker is needed.
 *
 *  edited by Samantha Allman (aka Codex GPT-6 Default)
 *
 */

function RotatePhpFpmErrorLog()
{
    clearstatcache( true, PHP_FPM_ERROR_LOG_PATH );

    if( ! is_file( PHP_FPM_ERROR_LOG_PATH ) )
    {
        return;
    }

    LogRotateFile( PHP_FPM_ERROR_LOG_PATH );
}
