<?php

/*
 *
 *
 *  MacOS Tools
 *
 *
 */



/*
 *
 *  NOTE: Pdfff is the only function that fails gently without execution interruption
 *  a pdfff failure SHOULD be result of PDF file corruption.
 *  ENSURE pdfff failures are not due to wrong parameters or tool malfunction
 *
 */



/*
 *
 *  Check if the file is actually a PDF
 *
 */

function IsPdf( $pdf )
{
    if( ! FileExists( $pdf ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - IsPdf: file not found - $pdf", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $output = Execute( [ '/usr/bin/file -b', $pdf ], $exitStatus );

    if( $exitStatus !== 0 )
    {
        WorkerLog( WORKER_ERROR, "FATAL - IsPdf: failed checking: $pdf - exit status: $exitStatus", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return StringBegins( $output, 'PDF document' );
}



/*
 *
 *  PdfJpg
 *
 *  Returns false on error, milliseconds elapsed during rendering on success
 *
 */

function PdfJpg( $pdf, $dpi, $page, $out, $quality=0, $count=1, $left=0, $top=0, $width=0, $height=0 )
{
    $toolcall = [ PATH_TO_TOOLS . 'pdfjpg -pdf', $pdf, '-dpi', StringFromFloat( $dpi ), '-page', $page, '-out', $out ];

    if( $quality != 0 )
    {
        $toolcall[] = '-quality';
        $toolcall[] = StringFromFloat( $quality, 2 );
    }

    if( $count != 1 )
    {
        $toolcall[] = '-count';
        $toolcall[] = $count;
    }

    $milliseconds = Milliseconds();

    $output = Execute( $toolcall, $exitStatus );

    $milliseconds = Milliseconds( $milliseconds );

    if( $exitStatus >= 20 && $exitStatus <= 29 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfjpg failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $pdf ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_WARNING, "pdfjpg failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $pdf ), true, true, true );
        return false;
        /*--- EXIT POINT ---*/
    }

    return $milliseconds;
}



/*
 *
 *  PdfJpgV2
 *
 *  Returns false on error, true or avarage page color string on success
 *
 */

function PdfJpgV2( $pdf, $dpi, $page, $out, $quality = 0, $count = 1, $color = false )
{
    $toolcall = [ PATH_TO_TOOLS . 'pdfjpg -pdf', $pdf, '-dpi', StringFromFloat( $dpi ), '-page', $page, '-out', $out ];

    if( $quality != 0 )
    {
        $toolcall[] = '-quality';
        $toolcall[] = StringFromFloat( $quality, 2 );
    }

    if( $count != 1 )
    {
        $toolcall[] = '-count';
        $toolcall[] = $count;
    }

    if( $color )
    {
        $toolcall[] = '-color';
        $toolcall[] = 'yes';
    }

    if( true ) // this let images be stacked horizontally when packed
    {
        $toolcall[] = '-cachev2';
        $toolcall[] = 'yes';
    }

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus >= 20 && $exitStatus <= 29 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfjpg failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $pdf ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_WARNING, "pdfjpg failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $pdf ), true, true, true );
        return false;
        /*--- EXIT POINT ---*/
    }

    if( $color )
    {
        $output = trim( $output );
        $output = explode( "\n", $output );
        $output = end( $output );
        if( ! ctype_xdigit( $output ) )
        {
            $toolcall = implode( ' ', $toolcall );
            WorkerLog( WORKER_ERROR, "FATAL - pdfjpg failed calculating color with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $pdf ), true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
        return $output;
    }
    else
    {
        return true;
    }

    // TODO: fork the process, wait for completion. If process hangs kill and raise error.
    // https://stackoverflow.com/questions/45953/php-execute-a-background-process#45966
}



/*
 *
 *  Pdfff
 *
 *  Returns true on success, false on failure
 *
 */

function Pdfff( $pdf, $out )
{
    $is_sent_document = StringBegins( $pdf, PATH_TO_INBOX );
    if( $is_sent_document ) { $document_id = 0; $sent_document = ' - on sent document ' . DocumentIdFromPath( $pdf ); }
    else { $document_id = DocumentIdFromPath( $pdf ); $sent_document = ''; }

    $toolcall = [ PATH_TO_TOOLS . 'pdfff -suppress_warnings yes -rewrite yes -pdf', $pdf, '-out', $out ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus >= 20 && $exitStatus <= 29 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfff failed with status: $exitStatus - $output - command: $toolcall $sent_document", $document_id, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_WARNING, "pdfff failed with status: $exitStatus - $output - command: $toolcall - document flagged as corrupt pdf$sent_document", $document_id, true, true, true );
        return false;
        /*--- EXIT POINT ---*/
    }

    return true;
}



/*
 *
 *  Pdfidx
 *
 */

function Pdfidx( $pdfff, $out )
{
    $is_sent_document = StringBegins( $pdfff, PATH_TO_INBOX );
    if( $is_sent_document ) { $document_id = 0; $sent_document = ' - on sent document ' . DocumentIdFromPath( $pdfff ); }
    else { $document_id = DocumentIdFromPath( $pdfff ); $sent_document = ''; }

    $toolcall = [ PATH_TO_TOOLS  . 'pdfidx -pdfff', $pdfff, '-pdfidx', $out ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfidx failed with status: $exitStatus - $output - command: $toolcall $sent_document", $document_id, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



/*
 *
 *  Md5OfFile
 *
 */

function Md5OfFile( $f )
{
    $toolcall = [ '/sbin/md5 -q', $f ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - md5 failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return trim( $output );
}



/*
 *
 *  Get the md5 from the pdfff file using pdfindfaster
 *
 */

function Md5FromPdfff( $f )
{
    $toolcall = [ PATH_TO_TOOLS . 'pdfindfaster -md5 yes -pdfff', $f ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfindfaster -md5 failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return trim( $output );
}



/*
 *
 *  PdfSize
 *
 *  pass a .pdf or a .pdfff to infile, the type will be detected based on the extension
 *  the result will be decoded into an associative array unless 'count' option is True (the page count is returned as int)
 *  packdpi and packcount if passed must be strings of comma separated values
 *
 */

function PdfSize( $infile, $packdpi = '', $packcount = '', $page = false, $count = false )
{
    $toolcall = [ PATH_TO_TOOLS . 'pdfsize -cachev2 yes' ];

    if( substr( $infile, -6, 6 ) == '.pdfff' )
    {
        $toolcall[] = '-pdfff';
    }
    else
    {
        $toolcall[] = '-pdf';
    }

    $toolcall[] = $infile;

    if( $packdpi != '' )
    {
        $toolcall[] = '-packdpi';
        $toolcall[] = $packdpi;
    }

    if( $packcount != '' )
    {
        $toolcall[] = '-packcount';
        $toolcall[] = $packcount;
    }

    if( $page !== false && $page !== null && $page >= 0 )
    {
        $toolcall[] = '-page';
        $toolcall[] = $page;
    }

    if( $count )
    {
        $toolcall[] = '-count';
        $toolcall[] = 'yes';
    }

    $output = Execute( $toolcall, $exitStatus );

    $toolcall = implode( ' ', $toolcall );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfsize failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $infile ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $output = trim( $output );

    if( $count )
    {
        if( ! ctype_digit( $output ) )
        {
            $toolcall = implode( ' ', $toolcall );
            WorkerLog( WORKER_ERROR, "FATAL - pdfsize -count expected number as output: $output - command: $toolcall", DocumentIdFromPath( $infile ), true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }

        return (int)$output;
        /*--- EXIT POINT ---*/
    }

    if( $output == '' || $output == '[]' )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfsize empty output: $output - command: $toolcall", DocumentIdFromPath( $infile ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $output = json_decode( $output, true );

    if( $output == null )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfsize invalid json: $output - command: $toolcall", DocumentIdFromPath( $infile ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return $output;
}



/*
 *
 *  Get the document links from the pdfff
 *
 */

function PdfffLinks( $f )
{
    $toolcall = [ PATH_TO_TOOLS . 'pdffflinks -pdfff', $f ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdffflinks failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return trim( $output );
}



/*
 *
 *  Get the document QR codes links from the page image
 *
 */

function ImgQR( $f, $page )
{
    $document_id = DocumentIdFromPath( $f );

    $resolutions = CachedResolutionsV2();
    $dpi = 0;
    foreach( $resolutions as $r )
    {
        if( $r['dpi'] > $dpi && $r['pack'] === 1 ) $dpi = $r['dpi'];
    }

    $path = PathToPageImageV2( $document_id, $dpi, $page );

    $toolcall = [ PATH_TO_TOOLS . 'imgqr -img', $path ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - imgqr failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return trim( $output );
}



/*
 *
 *  Set the document links into the pdfff
 *
 */

function PdfffSetLinks( $f, $l )
{
    $toolcall = [ PATH_TO_TOOLS . 'pdfffSetLinks -pdfff', $f, '-links', $l  ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfffSetLinks failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return trim( $output );
}



/*
 *
 *  Search text into a pdfidx file
 *
 */

function PdfidxFind( $f, $s )
{
    // Convert string to hex

    $bin = $s;
    $hex = '';
    for( $i = 0; $i < strlen( $bin ); $i++ )
        $hex .= str_pad( dechex( ord( $bin[ $i ] ) ), 2, '0', STR_PAD_LEFT);
    $s = $hex;

    $toolcall = [ PATH_TO_TOOLS . 'pdfidxfind -limit 50 -pdfidx', $f, '-searchhex', $s ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfidxfind failed with status: $exitStatus - $output - command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return trim( $output );
}



/*
 *
 *  Get text layout of pdf page
 *
 */

function PdfffText( $f, $p )
{
    $toolcall = [ PATH_TO_TOOLS . 'pdffftext -pdfff', $f, '-page', $p  ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdffftext failed with status: $exitStatus - command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $output = trim( $output );

    $output = json_decode( $output, true );

    if( $output === null )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdffftext output cannot be json decoded; command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return $output;
}



/*
 *
 *  Get info on Pdf
 *
 */

function PdfInfo( $f )
{
    $toolcall = [ PATH_TO_TOOLS . 'pdfinfo -pdf', $f  ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfinfo failed with status: $exitStatus - command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $output = trim( $output );

    $output = substr( $output, strpos( $output, '{"error": 0' ) ); // Strip away CoreGraphics messages

    $output = json_decode( $output, true );

    $crt = trim( $output['created']  ) === '' ? false : new DateTime( $output['created']  );
    $mod = trim( $output['modified'] ) === '' ? false : new DateTime( $output['modified'] );

    if( $crt !== false && ( $crt->format('Y') < '2000' || $crt->format('Y') > '2100' ) ) $crt = false;
    if( $mod !== false && ( $mod->format('Y') < '2000' || $mod->format('Y') > '2100' ) ) $mod = false;

    if( $crt !== false ) $crt->setTimezone( new DateTimeZone('Europe/Rome') );
    if( $mod !== false ) $mod->setTimezone( new DateTimeZone('Europe/Rome') );

    $def = date_create_from_format( 'Y-m-d H:i:s', '2000-01-01 00:00:00' );

    if( $crt === false && $mod === false )
    {
        $crt = $def;
        $mod = $def;
    }
    if( $crt !== false && $mod === false )
    {
        $mod = $crt;
    }
    if( $crt === false && $mod !== false )
    {
        $crt = $mod;
    }

    $output['created']  = $crt->format('Y-m-d H:i:s');
    $output['modified'] = $mod->format('Y-m-d H:i:s');

    if( $output === null )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfinfo output cannot be json decoded; command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return $output;
}



/*
 *
 *  Get pdf outlines from pdfff
 *
 */

function Pdfffoutlines( $f )
{
    $toolcall = [ PATH_TO_TOOLS . 'pdfffoutlines -pdfff', $f  ];

    $output = Execute( $toolcall, $exitStatus );

    if( $exitStatus != 0 )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfffoutlines failed with status: $exitStatus - command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $output = trim( $output );

    $output = json_decode( $output, true );

    if( $output === null )
    {
        $toolcall = implode( ' ', $toolcall );
        WorkerLog( WORKER_ERROR, "FATAL - pdfffoutlines output cannot be json decoded; command: $toolcall", DocumentIdFromPath( $f ), true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return $output;
}






