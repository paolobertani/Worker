<?php

/*
 *
 *
 *  Log rotation
 *
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



function LogRotateDir( $root )
{
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



function LogRotateFile( $file )
{
    if( substr( $file, -4, 4 ) !== '.log' )
    {
        return;
        /*--- EXIT POINT ---*/
    }

    if( GetFileSize( $file ) <= LOG_SIZE )
    {
        return;
        /*--- EXIT POINT ---*/
    }

    file_put_contents( $file, substr( file_get_contents( $file ), -LOG_SIZE, LOG_SIZE ) );
}


