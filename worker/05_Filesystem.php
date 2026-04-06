<?php

/*
 *
 *
 *  Filesystem
 *
 *
 */


/*
 *
 *  Execute a command line tool
 *  Accepts a string or array of strings
 *  When an array is passed arguments are escaped except first
 *
 *  Note: stderr is redirected into stdout;
 *  both are catched into `$output`
 *  none go directly on the terminal
 *
 */

function Execute( $cmd, &$exitStatus )
{
    $output = array();
    $exitStatus = 0;

    if( is_array( $cmd ) )
    {
        $arr = $cmd;

        $cmd = $arr[0];

        for( $i = 1; $i < count($arr); $i++ )
        {
            $cmd .= ' ' . escapeshellarg( $arr[ $i ] );
        }
    }

    $cmd .= " 2>&1"; // send stderr to stdout catching both

    exec( $cmd, $output, $exitStatus );

    $output = implode( "\n", $output );

    return $output;
}



/*
 *
 *  The given full path points to an existing file
 *
 */

function FileExists( $f )
{
    clearstatcache( true );
    return is_file( $f );
}


/*
 *
 *  The given full path points to an existing directory
 *
 */

function DirectoryExists( $d )
{
    clearstatcache( true );
    return is_dir( $d );
}



/*
 *
 *  Make all the directories to build up the full path provided
 *  If fails return the error kind.
 *
 */

function MakeDirectoryTree( $d )
{
    $result = mkdir( $d, 0755, true );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - MakeDirectoryTree: failed - $d", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}


/*
 *
 *  Given a full path return a list with the filenames (not full paths) of the files (not directories)
 *  in that directory. The directory must exists otherwise an error is raised.
 *
 *  NOTE:
 *  Items that begins with dot `.` are excluded
 *  Symbolic links are excluded
 *
 */

function FilesInDirectory( $d )
{
    if( ! DirectoryExists( $d ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - FilesInDirectory: not a directory - $d", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $list = scandir( $d );

    $files = array();

    PathAppendSlash( $d );

    foreach( $list as $item )
    {
        if( is_file( "$d$item" ) && substr( $item, 0, 1 ) != '.' && ! is_link( "$d$item" ) )
        {
            $files[] = $item;
        }
    }

    return $files;
}


/*
 *
 *  Given a full path return a list with the names (not full paths) of the directories
 *  in that directory. The directory must exists otherwise an error is raised.
 *
 *  NOTE:
 *  Items that begins with dot `.` are excluded
 *  Symbolic links are excluded
 *
 */

function DirectoriesInDirectory( $d )
{
    if( ! DirectoryExists( $d ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - DirectoriesInDirectory: not a directory - $d", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $list = scandir( $d );

    $dirs = array();

    PathAppendSlash( $d );

    foreach( $list as $item )
    {
        if( is_dir( "$d$item" ) && substr( $item, 0, 1 ) != '.' && ! is_link( "$d$item" ) )
        {
            $dirs[] = $item;
        }
    }

    return $dirs;
}



/*
 *
 *  Remove the file at the path provided
 *  For safety checks related document id is passed
 *
 */

function RemoveFile( $path, $document_id )
{
    if( ! FileExists( $path ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - RemoveFile: file not found - $path", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( ! PathIsSafe( $path, $document_id ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - RemoveFile: operation not permitted - path: $path", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $result = unlink( $path );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - RemoveFile: failed to delete - $path", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



/*
 *
 *  Remove the directory at the path provided and everything it contains
 *  For safety checks related document id is passed
 *
 */

function RemoveDirectory( $path, $document_id )
{
    if( ! DirectoryExists( $path ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - RemoveDirectory: directory not found - $path", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( ! PathIsSafe( $path, $document_id ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - RemoveDirectory: operation not permitted - path: $path", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $output = Execute( array( 'rm -rf', $path ), $exitStatus );

    if( $exitStatus !== 0 )
    {
        WorkerLog( WORKER_ERROR, "FATAL - RemoveDirectory: failed to delete - $path - exit status $exitStatus", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



/*
 *
 *  Returns the size of a file
 *
 */

function GetFileSize( $f )
{
    clearstatcache( true );

    $size = filesize( $f );

    if( $size === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - FileSize: failed to get size - $f", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return $size;
}



/*
 *
 *  Returns the size of the whole contents of a directory
 *
 */

function GetDirectorySize( $d )
{
    PathAppendSlash( $d );

    $files = FilesInDirectory( $d );
    $size = 0;

    foreach( $files as $f )
    {
        $sz = filesize( "$d$f" );
        if( $sz === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - DirectorySize: failed to get size - $d$f", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }

        $size += $sz;
    }

    $dirs = DirectoriesInDirectory( $d );

    foreach( $dirs as $dir )
    {
        $size += GetDirectorySize( $d . $dir );
    }

    return $size;
}



/*
 *
 *  Append a trailing slash to a path if missing
 *
 */

function PathAppendSlash( &$path )
{
    if( substr( $path, -1, 1 ) != '/' )
    {
        $path .= '/';
    }
}