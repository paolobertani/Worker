<?php

/*
 *
 *
 *  Run
 *
 *
 */



/*
 *
 *  Autolocked: clear & rebuild everything
 *
 */

function RunDocumentAutolocked()
{
    $document = DbDocumentAutolocked();
    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document_id = $document['id'];
    $hd = 1;
    MakePathToCacheV2Maybe( $document_id );
    ConsistencyCheck( $document_id );

    WorkerLog( WORKER_INFO, "Detected locked document. Rebuild all.", $document_id, true, false, 1 );

    DbDocumentLock( $document_id );
    $document['worker_cmd'] = 'all';
    DbDocumentUpdateAndUnlock( $document );

    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Document with command
 *
 */

function RunDocumentWithCommand()
{
    $document = DbDocumentWithCommand();
    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }


    // Document and command

    $document_id = $document['id'];
    $command = $document['worker_cmd'];
    DbDocumentLock( $document_id );


    // Check command is valid

    if( ! CommandSupported( $command ) )
    {
        WorkerLog( WORKER_WARNING, "Ignored unkwnown command: $command", $document_id, true, true, true );
        $document['worker_cmd'] = '';
        DbDocumentUpdateAndUnlock( $document );
        return true;
        /*--- EXIT POINT ---*/
    }


    // Handle a command on a document without pdf or deleted

    if( $document['pdf'] == 0 || $document['status'] == 'DELETED' )
    {
        WorkerLog( WORKER_NOTICE, "Issued command '$command' on a document with missing Pdf or deleted", $document_id, true, true, true );
        $document['worker_cmd'] = '';
        DbDocumentUpdateAndUnlock( $document );
        return true;
        /*--- EXIT POINT ---*/
    }


    // Build cache directory if needed

    if( $command == 'all' || $command == 'cache' )
    {
        MakePathToCacheV2Maybe( $document_id );
    }


    // cache v2: just inval the cachev2_md5 to rebuild the cache

    if( $command == 'all' || $command == 'cache' )
    {
        $document['cachev2_md5'] = '';
    }


    // Check requirements to handle a command on a regular document

    $command_requires_pdfff = ! in_array( $command, [ 'all', 'pdfff' ] );
    $command_requires_pdfidx = ! in_array( $command, [ 'all', 'pdfff', 'pdfidx' ] );

    ConsistencyCheck( $document_id, $command_requires_pdfff, $command_requires_pdfidx );


    // Execute the command

    $result = ExecuteCommand( $command, $document );

    $document['worker_cmd'] = '';

    if( $result )
    {
        if( $command == 'all' || $command == 'pdfff' || $command == 'pdfidx' )
        {
            $document['pdfff_size'] = DocumentPdfffSize( $document_id ) + DocumentPdfidxSize( $document_id );
        }

        if( $command == 'all' || $command == 'covers' )
        {
            $cover = DocumentCoverMeasure( $document_id );
            $document['covers_md5']         = '';
            $document['cover_width']        = $cover['width'];
            $document['cover_height']       = $cover['height'];
        }

        if( CommandSupported( $command ) ) // A command was executed
        {
            $document['pdf'] = 1;
        }
    }
    else
    {
        $document['pdf'] = -1;
    }

    $document['cache_size'] = DocumentCacheSize( $document_id );

    DbDocumentUpdateAndUnlock( $document );
    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Document to cover
 *
 */

function RunDocumentToCover()
{
    $document = DbDocumentToCover();
    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }


    $document_id = $document['id'];
    $hd = 1;
    MakePathToCacheV2Maybe( $document_id );
    ConsistencyCheck( $document_id );

    WorkerLog( WORKER_INFO, "Rendering covers", $document_id, true, false, 1 );

    DbDocumentLock( $document_id );

    RemoveCovers( $document_id );

    $result = RenderCoversForEachResolution( $document_id, $hd );

    if( $result )
    {
        $cover = DocumentCoverMeasure( $document_id );

        $document['covers_md5']   = $document['md5'];
        $document['cover_width']  = $cover['width'];
        $document['cover_height'] = $cover['height'];
    }
    else
    {
        $document['pdf'] = -1;
    }

    DbDocumentUpdateAndUnlock( $document );
    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Document to cacheV2
 *
 */

function RunDocumentToCacheV2()
{
    if( WorkerShouldPause( WORKER_CACHE_PAUSE ) )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document = DbDocumentToCacheV2();
    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document_id   = $document['id'];
    $md5           = $document['md5'];
    $cachev2_md5   = $document['cachev2_md5'];
    $cachev2_pages = $document['cachev2_pages'];

    // get pages count from the pdfff instead that from the db
    $pdfff = PathToPdfff( $document_id );
    $pagesCount = PdfSize( $pdfff, '', '', false, true );

    MakePathToCacheV2Maybe( $document_id );
    ConsistencyCheck( $document_id );

    if( $md5 !== $cachev2_md5 )
    {
        $cachev2_pages = 0;
    }

    $firstPageNum = $cachev2_pages;
    $lastPageNum  = $firstPageNum + ( WORKER_CACHE_PAGE_LOTS === 0 ? 99999 : WORKER_CACHE_PAGE_LOTS );
    if( $lastPageNum >= $pagesCount )
    {
        $lastPageNum = $pagesCount - 1;
    }
    $cachev2_pages = $lastPageNum + 1;

    WorkerLog( WORKER_INFO, "Rendering cached V2 pages "  . ($firstPageNum+1) . "-" . ($lastPageNum+1) . " : $pagesCount", $document_id, true, false, 1 );

    DbDocumentLock( $document_id );

    if( $md5 !== $cachev2_md5 )
    {
        RemoveCacheV2( $document_id );
        MakePathToCacheV2Maybe( $document_id );
        file_put_contents( PathToDocument( $document_id ) . "$document_id.pagescolor.txt", "" );
    }

    $result = RenderPagesForEachResolutionV2( $document_id, $firstPageNum, $lastPageNum, $pagesCount );

    if( ! $result )
    {
        $document['pdf'] = -1;
        $document['pages_count'] = $pagesCount;
    }
    else
    {
        $document['cachev2_md5'] = $md5;                // `cachev2_md5` is set to `md5` as cache rendering starts
        $document['cachev2_pages'] = $lastPageNum + 1;  // cache rendering is over when `cachev2_pages` equals `pages_count`
        $document['pages_count'] = $pagesCount;

        if( WORKER_CACHE_MAKES_COLORS )
        {
            $document['pagescolor_md5'] = $md5;
        }
    }

    // (Re)build pdfidx as sometimes it's corrupt when coming from the inbox - to be fixed...

    if( $lastPageNum === $pagesCount - 1 )
    {
        Pdfidx( $pdfff, PathToPdfidx( $document_id ) );
    }

    $document['cache_size'] = DocumentCacheSize( $document_id );

    DbDocumentUpdateAndUnlock( $document );
    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Document with page-colors to be produced
 *
 */

function RunDocumentToColor()
{
    $document = DbDocumentToColor();
    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document_id = $document['id'];

    ConsistencyCheck( $document_id );

    WorkerLog( WORKER_INFO, "Producing pages colors string", $document_id, true, false, 1 );

    DbDocumentLock( $document_id );

    $colors = PagesColorString( $document );

    if( $colors !== false )
    {
        $document['pagescolor_md5'] = $document['md5'];
        file_put_contents( PathToDocument( $document_id ) . "$document_id.pagescolor.txt", $colors );
    }
    else
    {
        $document['pagescolor_md5'] = '<FAILED>';
        WorkerLog( WORKER_WARNING, "Producing pages colors string failed", $document_id, true, true, true );
    }

    DbDocumentUpdateAndUnlock( $document );
    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Document with QR codes links to be produced
 *
 */

function RunDocumentToQR()
{
    $document = DbDocumentToQR();
    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document_id = $document['id'];

    ConsistencyCheck( $document_id );

    WorkerLog( WORKER_INFO, "Producing QR codes links", $document_id, true, false, 1 );

    DbDocumentLock( $document_id );

    $qr_count = QRTagPdfff( $document_id );

    if( $qr_count === false )
    {
        $document['qr_md5'] = '<FAILED>';
        $document['qr_count'] = 0;
        WorkerLog( WORKER_WARNING, "Producing QR codes links failed", $document_id, true, true, true );
    }
    else
    {
        $document['qr_md5'] = $document['md5'];
        $document['qr_count'] = $qr_count;
    }

    DbDocumentUpdateAndUnlock( $document );
    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Document to discard cache v2
 *
 */

function RunDocumentToUncacheV2()
{
    $document = DbDocumentToUncacheV2();
    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document_id = $document['id'];
    ConsistencyCheck( $document_id );

    WorkerLog( WORKER_INFO, "Discarding cache of `dont_cache` document", $document_id, true, false, 1 );

    DbDocumentLock( $document_id );

    RemoveCacheV2( $document_id );

    $document['cachev2_md5'] = '';

    $document['cache_size'] = DocumentCacheSize( $document_id );

    DbDocumentUpdateAndUnlock( $document );
    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Document to delete
 *
 */

function RunDocumentToRemove()
{
    $document = DbDocumentToRemove();
    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }


    $document_id = $document['id'];

    WorkerLog( WORKER_INFO, "Discarding document marked as deleted", $document_id, true, false, 1 );

    DbDocumentLock( $document_id );

    RemoveDirectory( PathToDocument( $document_id ), $document_id );

    $document['pdf']                = 0;
    $document['pdf_size']           = 0;
    $document['pdfff_size']         = 0;
    $document['cover_width']        = 0.0;
    $document['cover_height']       = 0.0;
    $document['cache_size']         = 0;
    $document['has_slow_pages']     = 0;
    $document['slow_pages']         = '[]';
    $document['slow_milliseconds']  = '[]';


    DbDocumentUpdateAndUnlock( $document );
    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Document to generate `pdfff` and `pdfidx` in SentDocuments
 *
 */

function RunSentDocumentToPdfff()
{
    $sent_document = DbSentDocumentToPdfff();
    if( $sent_document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $sent_document_id = $sent_document['id'];

    // Manage the unlikely case a record is just created but the file not moved in place yet

    if( ! FileExists( PATH_TO_INBOX . "$sent_document_id.pdf" ) )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    // --

    WorkerLog( WORKER_INFO, "Generating sent document pdfff+pdfidx: $sent_document_id", 0, true, false, 1 );

    $result = SentDocumentPdfffPdfidx( $sent_document_id );

    if( $result )
    {
        $sent_document[ 'pdfff' ] = 1;
    }

    if( ! $result )
    {
        $sent_document[ 'in_inbox' ] = 0;
        $sent_document[ 'status' ] = -1;
        $sent_document[ 'reply' ] = '{rd_bad_pdf}';
    }

    DbSentDocumentUpdate( $sent_document );
    WorkerAlive();

    return true;
}



/*
 *
 *  Document to apply/remove idrolab tags
 *
 */

function RunDocumentToIdrolabTag( $tag )
{
    if( ! WORKER_ENABLE_IDROLAB_TAGS )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    if( WorkerShouldPause( IDR_PAUSE ) ) // Pause during backup
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document = $tag ? DbDocumentToIdrolabTag() : DbDocumentToIdrolabUnTag();

    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document_id = $document['id'];

    // These are RO and will not reapplied upon unlock

    $brand_id = $document['brand_id'];
    $brand = $document['brand'];
    $description = $document['description'];
    unset( $document['brand_id'] );
    unset( $document['brand'] );
    unset( $document['description'] );

    ConsistencyCheck( $document_id );

    $action = $tag ? "Applying" : "Removing";
    WorkerLog( WORKER_INFO, "$action idrolab tags to pdfff: $brand - $description", $document_id, true, false, 1 );

    DbDocumentLock( $document_id );


    // Load productlist

    if( $tag )
    {
        $plFile = MASTER_STORAGE_DIR . "/brands/$brand_id/productlist.txt";

        if( ! is_file( $plFile ) )
        {
            WorkerLog( WORKER_WARNING, "Brand $brand_id has no productlist.txt file", $document_id, true, true, true );
            DbBrandWithNoIdrolabProductList( $brand_id );
            return false;
            /*--- EXIT POINT ---*/
        }

        $productlist = file_get_contents( $plFile );
    }
    else
    {
        $productlist = false;
    }


    // Apply/Remove tags

    $status = IdrolabTagPdfff( $document_id, $productlist );


    // Save status and pdfff size

    $document['idrolab_status'] = $status;
    $document['pdfff_size'] = DocumentPdfffSize( $document_id ) + DocumentPdfidxSize( $document_id );

    DbDocumentUpdateAndUnlock( $document );
    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Document with metadata to be produced
 *
 */

function RunDocumentToMeta()
{
    $document = DbDocumentToMeta();
    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document_id = $document['id'];
    $hd = 1;
    MakePathToCacheV2Maybe( $document_id );
    ConsistencyCheck( $document_id );

    WorkerLog( WORKER_INFO, "Producing document metadata", $document_id, true, false, 1 );

    DbDocumentLock( $document_id );

    $quickMeta = [];

    $result = MetaDataProduce( $document_id, $quickMeta );

    if( $result )
    {
        $document['meta_md5']     = $document['md5'];
        $document['pdf_modified'] = $quickMeta['pdf_modified'];
        $document['pdf_created']  = $quickMeta['pdf_created'];
        $document['has_outlines'] = $quickMeta['has_outlines'];
        $document['pages_count']  = $quickMeta['pages_count'];
    }

    DbDocumentUpdateAndUnlock( $document );
    WorkerAlive();

    return $document_id;
}



/*
 *
 *  Document with missing `md5`
 *
 */

function RunDocumentToFixMD5()
{
    $document = DbDocumentToFixMD5();
    if( $document === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $document_id = $document['id'];

    ConsistencyCheck( $document_id );

    WorkerLog( WORKER_INFO, "Fixing missing `md5` on document", $document_id, true, false, 1 );

    DbDocumentLock( $document_id );

    $md5 = DocumentMd5( $document_id );

    if( $md5 === '' )
    {
        WorkerLog( WORKER_WARNING, "Cannot get MD5 of document", $document_id, true, true, true );
    }

    $document['md5'] = $md5;

    DbDocumentUpdateMD5AndUnlock( $document );
    WorkerAlive();

    return $document_id;
}
