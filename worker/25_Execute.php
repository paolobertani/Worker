<?php

//
//
// Immediate execution
//
//



//
// Set a worker_cmd command
//

function WorkerExecuteCmd( $cmd, $id )
{
    // check command is supported

    if( ! CommandSupported( $cmd ) )
    {
        EchoNL( "Unsupported command `$cmd`" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }


    // check document exists

    $error = '';
    $result = QueryExecute( '10_document_get.sql', $error, [ 'id' => $id ] );
    if( $result === false )
    {
        EchoNL( "10_document_get.sql: query failed - Error: $error" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( ! is_array( $result ) ||  count( $result ) === 0 )
    {
        EchoNL( "document not found with id `$id`" );
        WorkerQuitNow();
        /*--- EXIT POINT ---*/
    }


    // check document has no command pending

    if( $result[0]['worker_cmd'] !== '' )
    {
        EchoNL( "document has `{$result[0]['worker_cmd']}` command pending" );
        WorkerQuitNow();
        /*--- EXIT POINT ---*/
    }


    // set the command

    $error = '';
    $result = QueryExecute( '30_document_set_cmd.sql', $error, [ 'id' => $id, 'worker_cmd' => $cmd ] );
    if( $result === false )
    {
        EchoNL( "30_document_set_cmd.sql: query failed - Error: $error" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



//
// Sync document directory on slave VOLUME
//

function WorkerExecuteSync( $id )
{
    // chack slave is present

    if( ! SlaveIsPresent() )
    {
        EchoNL( "SLAVE volume not present" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }


    // check document exists via DB

    $error = '';
    $result = QueryExecute( '10_document_get.sql', $error, [ 'id' => $id ] );
    if( $result === false )
    {
        EchoNL( "10_document_get.sql: query failed - Error: $error" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( ! is_array( $result ) ||  count( $result ) === 0 )
    {
        EchoNL( "document not found with id `$id`" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }


    // check document has no command pending and is not locked

    if( $result[0]['worker_cmd'] !== '' )
    {
        EchoNL( "document has `{$result[0]['worker_cmd']}` command pending" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( $result[0]['lock'] !== '' )
    {
        EchoNL( "document is locked by `$result[0]['lock']`" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }


    // rsync dirs

    SlaveSync( $id );
}


