<?php

//
//
// Slave
//
//



//
// Returns true if the slave is present
//

function SlaveIsPresent()
{
    return ( SLAVE_STORAGE_DIR != '' && SLAVE_STORAGE_DIR != MASTER_STORAGE_DIR );
}



//
// Sync document files in the slave volume
// if slave disk is present
//

function SlaveSync( $document_id )
{
    // No op if slave is not present

    if( ! SlaveIsPresent() )
    {
        return;
        /*--- EXIT POINT ---*/
    }


    // Terminal log

    WorkerLog( WORKER_INFO, "Sync slave to master", $document_id, false, false, 1 );


    // Path to document directories

    $source = PathToDocument( $document_id );
    $target = PathToDocumentInSlave( $document_id );


    // If document directory is missing delete on the slave volume

    if( ! DirectoryExists( $source ) )
    {
        if( DirectoryExists( $target ) )
        {
            RemoveDirectory( $target, $document_id );
            return;
            /*--- EXIT POINT ---*/
        }
    }


    // If document directory is missing on target then create it

    if( ! DirectoryExists( $target ) )
    {
        MakeDirectoryTree( $target );
    }


    // Adjust paths for rsync

    if( substr( $source, -1, 1 ) != '/' )
    {
        $source = "$source/";
    }

    if( substr( $target, -1, 1 ) == '/' )
    {
        $target = substr( $target, 0, -1 );
    }


    // Use rsync to align slave to master

    $toolcall = [ '/usr/bin/rsync -a --delete', $source, $target ];

    $output = Execute( $toolcall, $exitStatus );


    // Log error if any

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "SlaveSync: failed with status: $exitStatus - $output - command: $toolcall", $document_id, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



//
// SlaveSyncBrands
//

function SlaveSyncBrands( $brands )
{
    if( ! is_array( $brands ) )
    {
        $brands = [ $brands ];
    }


    // No op if slave is not present

    if( ! SlaveIsPresent() )
    {
        return;
        /*--- EXIT POINT ---*/
    }


    // Terminal log

    WorkerLog( WORKER_INFO, "Sync slave brands to master", 0, false, false, 1 );


    foreach( $brands as $b )
    {

        // Path to directories

        $source = PathToBrandDirectory( $b );
        $target = PathToBrandDirectoryInSlave( $b );


        // If document directory is missing on target then create it

        if( ! DirectoryExists( $target ) )
        {
            MakeDirectoryTree( $target );
        }


        // Adjust paths for rsync

        if( substr( $source, -1, 1 ) != '/' )
        {
            $source = "$source/";
        }

        if( substr( $target, -1, 1 ) == '/' )
        {
            $target = substr( $target, 0, -1 );
        }


        // Use rsync to align slave to master

        $toolcall = [ '/usr/bin/rsync -a --delete', $source, $target ];

        $output = Execute( $toolcall, $exitStatus );


        // Log error if any

        if( $exitStatus != 0 )
        {
            $toolcall = implode( ' ', $toolcall );
            WorkerLog( WORKER_ERROR, "SlaveSync: failed with status: $exitStatus - $output - command: $toolcall", $document_id, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
    }
}
