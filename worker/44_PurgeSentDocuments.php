<?php

//
//
// Purge sent documents
//
//

function PurgeSentDocuments()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Purge sent documents...", 0, false, false, 1 );

    // Delete sent documents without an uploaded file

    $error = '';
    $result = QueryExecute( '53_ready_sent_documents.sql', $error, [] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 53_ready_sent_documents.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $pi = PATH_TO_INBOX;
    $count = 0;

    foreach( $result as $sentdoc )
    {
        $id = $sentdoc['id'];

        if( FileExists( "$pi$id.pdf" ) && FileExists( "$pi$id.pdfff" ) && FileExists( "$pi$id.pdfidx" ) )
        {
            // OK
        }
        else
        {
            $error = '';
            $result2 = QueryExecute( '54_delete_sent_document.sql', $error, [ 'id' => $id ] );
            if( $result2=== false )
            {
                WorkerLog( WORKER_ERROR, "FATAL - 54_delete_sent_document.sql: query failed - Error: $error", 0, true, true, true );
                WorkerQuitNow();
                /*--- QUIT POINT ---*/
            }
            $count++;
        }
    }

    // Flag as managed sent documents with the md5 matching with an existing document

    $error = '';
    $result = QueryExecute( '55_fix_sent_documents_1.sql', $error, [] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 55_fix_sent_documents_1.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    // Flag as managed sent documents without md5 and without uplaoded files

    $error = '';
    $result = QueryExecute( '56_fix_sent_documents_2.sql', $error, [] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 56_fix_sent_documents_2.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'sent_documents' );

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Deleted $count sent documents: $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}
