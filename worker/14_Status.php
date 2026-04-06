<?php

/*
 *
 *
 *  Status
 *
 *
 */



function StatusGetValue( $key )
{
    $status = StatusLoadPrivate();
    if( isset( $status[$key] ) )
    {
        return $status[$key];
    }
    else
    {
        return null;
    }
}



function StatusSetValue( $key, $value )
{
    $status = StatusLoadPrivate();
    $status[$key] = $value;
    StatusSavePrivate( $status );
}



// -----



function StatusLoadPrivate()
{
    if( ! is_file( PATH_TO_STATUS ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - status file not present", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $json = file_get_contents( PATH_TO_STATUS );
    if( $json === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - failed reading status file", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $data = json_decode( $json, true );
    if( $data === null )
    {
        WorkerLog( WORKER_ERROR, "FATAL - failed json decoding status file", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return $data;
}



function StatusSavePrivate( $data )
{
    if( false === file_put_contents( PATH_TO_STATUS, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - failed writing status file", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}


