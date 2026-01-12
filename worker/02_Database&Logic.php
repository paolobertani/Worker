<?php

//
//
// Database&Logic
//
//



//
// Performs a query
// Stops execution on fail
// Returns false for no record or a single document data as associative array
// Returns the number of affected rows for INSERT, UPDATE, DELETE
//

function DbDocumentQuery( $query, $params = null )
{
    $error = '';
    $result = QueryExecute( $query, $error, $params );

    if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }

    if( ! is_array( $result ) )
    {
        return $result;
        /*--- EXIT POINT ---*/
    }

    if( count( $result ) === 0 )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    return $result[ 0 ];
}


//
// Document PDF is removed after 1 month the document was marked as deleted
// `deleted` < (NOW -1M)
//

function DocumentPdfRemoveLimit()
{
    $time = strtotime( "-1 month", time() );
    $deleted = date( 'Y/m/d H:i:s', $time);
    return $deleted;
}



//
//
// The queries
//
//

// NOTE: every query returns false (no document) or an associative array with the following single document data.
// During operation values affected must be updated
// Upon UNLOCK&UPDATE all the values are passed to the query in order to update the record
//



function DbDocumentAutolocked()
{
    return DbDocumentQuery( '01_document_autolocked.sql' );
}



function DbDocumentWithCommand()
{
    return DbDocumentQuery( '02_document_with_command.sql' );
}



function DbDocumentToFixMD5()
{
    return DbDocumentQuery( '18_document_to_fix_md5.sql' );
}



function DbDocumentToCover()
{
    return DbDocumentQuery( '03_document_to_cover.sql' );
}



function DbDocumentToCacheV2()
{
    return DbDocumentQuery( '04_document_to_cachev2.sql' );
}



function DbDocumentToColor()
{
    return DbDocumentQuery( '12_document_to_color.sql' );
}



function DbDocumentToQR()
{
    return DbDocumentQuery( '19_document_to_qr.sql' );
}



function DbDocumentToMeta()
{
    return DbDocumentQuery( '11_document_to_meta.sql' );
}



function DbDocumentToUncacheV2()
{
    return DbDocumentQuery( '06_document_to_uncachev2.sql' );
}



function DbDocumentToRemove()
{
    $deleted = DocumentPdfRemoveLimit();

    $params = [ 'deleted' => $deleted ];

    return DbDocumentQuery( '07_document_to_remove.sql', $params );
}



function DbSentDocumentToPdfff()
{
    return DbDocumentQuery( '15_sent_document_to_pdfff.sql' );
}



function DbDocumentToIdrolabTag()
{
    $params = [ 'expire' => date( 'Y-m' ) ];

    return DbDocumentQuery( '40_document_to_idrolab_tag.sql', $params );
}



function DbDocumentToIdrolabUnTag()
{
    return DbDocumentQuery( '42_document_to_idrolab_untag.sql' );
}


function DbBrandWithNoIdrolabProductList( $brand_id )
{
    $params = [ 'brand_id' => $brand_id ];

    return DbDocumentQuery( '41_brand_without_idrolab_pl.sql', $params );
}



//
//
// Lock / Unlock / Update
//
//



function DbDocumentLock( $document_id )
{
    $params = [ 'id' => $document_id ];

    return DbDocumentQuery( '08_document_lock.sql', $params );
}



function DbDocumentUpdateAndUnlock( $document )
{

    // Remove fields read-only

    unset( $document['md5'] );
    unset( $document['status'] );
    unset( $document['expire'] );


    // Perform query

    return DbDocumentQuery( '09_document_update&unlock.sql', $document );
}



function DbSentDocumentUpdate( $sent_document )
{
    return DbDocumentQuery( '16_sent_document_update.sql', $sent_document );
}



function DbDocumentUpdateMD5AndUnlock( $document )
{
    $params = [
        'id'  => $document['id'],
        'md5' => $document['md5']
    ];

    return DbDocumentQuery( '09_document_update_md5&unlock.sql', $params );
}



// ----------------------
// the fields:
// ----------------------
//
// RO   id
//      worker_cmd
//      cover_width
//      cover_height
// RO   hd
//      pdf
//      cache_size
//      pdf_size
//      pdfff_size
// RO   status
// RO   expire
//      slow_pages
//      slow_milliseconds
//      has_slow_pages
//      idrolab_status
//      pdf_modified
//      pdf_created
//      has_outlines
//      pages_count
// RO   md5
//      cachev2_md5
//      cachev2_pages
//      covers_md5
//      pagescolor_md5
//      qr_md5
//      qr_count
//      meta_md5
