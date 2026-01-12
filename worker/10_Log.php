<?php

//
//
// Log
//
//


//
// GLOBALS
//

$gCurrentDayNumber = '0';
$gEmailCountToday = 0;



//
// CONSTANTS
//

define( 'WORKER_INFO',          1 );
define( 'WORKER_NOTICE',        2 );
define( 'WORKER_WARNING',       3 );
define( 'WORKER_ERROR',         4 );



//
// Log information, warning or error via db / email / terminal
// If document id doesn't apply pass zero or NULL
//

function WorkerLog( $type, $message, $document_id, $db = true, $email = true, $terminal = true )
{
    // Globals

    global $gCurrentDayNumber;
    global $gEmailCountToday;

    global $gWorkerImmediate;


    // Overwrite arguments for immediate mode

    if( $gWorkerImmediate )
    {
        $db = false;
        $email = false;
        $terminal = true;
    }


    // Worker type

    if( HEAVY_DUTY )
    {
        $message = "<HDTY> $message";
    }


    // Write on terminal

    $pad = "                     ";

    if( $terminal === true )
    {
        EchoNL( date( 'd/m/Y H:i:s' ) . ' - ' . WorkerLogPreambleForType( $type ) . str_replace( ' - ', "\n$pad ", $message ) . ( $document_id ? "\n$pad Document id: $document_id" : "" ) );
    }
    if( $terminal === 1 )
    {
        EchoCR( date( 'd/m/Y H:i:s' ) . ' - ' . WorkerLogPreambleForType( $type ) . $message . ( $document_id ? " - Document id: $document_id" : "" ) );
    }


    // Send an email

    $today = date( 'd' );
    if( $gCurrentDayNumber != $today )
    {
        $gCurrentDayNumber = $today;
        $gEmailCountToday = 0;
    }

    $email_error = '';
    if( $email && $gEmailCountToday < WORKER_MAX_EMAILS )
    {
        $email_error = WorkerLogEmail( $type, $message, $document_id );

        if( $email_error == '' )
        {
            $gEmailCountToday++;

            if( $gEmailCountToday == WORKER_MAX_EMAILS )
            {
                WorkerLogEmail( WORKER_WARNING, 'Maximum number of daily email sent - Restart the worker to reset the count', 0 );
            }
        }
    }


    // Log to the db

    $db_error = '';
    if( $db )
    {
        $db_error = WorkerLogDB( $type, $message, $document_id );
    }


    // Handle errors occured during log

    if( $email_error )
    {
        WorkerLog( WORKER_ERROR,
                   "failed to send email to " . WORKER_EMAIL_TO . " : " . $email_error,
                   null,
                   $db_error==='',
                   false );
    }

    if( $db_error )
    {
        WorkerLog( WORKER_ERROR,
                   "failed writing log: " . $db_error,
                   null,
                   false,
                   $email_error==='' );
    }
}



function WorkerLogEmail( $type, $message, $document_id )
{
    $recipients = explode( '+', WORKER_EMAIL_TO );

    $subject = WorkerLogSubjectForType( $type );
    $preamble = WorkerLogPreambleForType( $type );
    $timestamp = date( 'd/m/Y H:i:s' );
    $machine = WORKER_MACHINE;
    $text = "$preamble\n\n" . str_replace( ' - ', "\n", $message ) . "\n\n" . ( $document_id ? "document id: $document_id\n\n" : "" ) . "$timestamp\n\n$machine";
    $html = "<span style='font-family: \"Helvetica Neue\"; font-size: 16px;'><strong>$preamble</strong></span><br><br><span style='font-family: \"Monaco\"; font-size: 14px;'>" . str_replace( ' - ', "<br>", $message ) . "</span><br><br>\n\n" . ( $document_id ? "<span style='font-family: \"Helvetica Neue\"; font-size: 14px;'>Document id: $document_id</span><br><br>\n\n" : "" ) . "<span style='font-family: \"Helvetica Neue\"; font-size: 14px;'>When: $timestamp</span><br><br>\n\n<span style='font-family: \"Helvetica Neue\"; font-size: 14px;'>Machine: $machine</span>";

    $mailer = new PHPMailer\PHPMailer\PHPMailer( true );

    $error = '';

    try
    {
        $mailer->IsSMTP();                                              //  Set mailer to use SMTP

        $mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mailer->Host       = WORKER_EMAIL_HOST;                        //  Specify main and backup server
        $mailer->SMTPAuth   = WORKER_EMAIL_AUTH;                        //  Enable SMTP authentication
        $mailer->Username   = WORKER_EMAIL_USER;                        //  SMTP username
        $mailer->Password   = WORKER_EMAIL_PASS;                        //  SMTP password
        $mailer->SMTPSecure = WORKER_EMAIL_SCRE;                        //  Enable encryption, ( '' || 'tls' || 'ssl' )
        $mailer->Port       = WORKER_EMAIL_PORT;                        //  Port

        $mailer->From       = WORKER_EMAIL_FROM;
        $mailer->FromName   = WORKER_EMAIL_NAME;
        foreach( $recipients as $recipient )
        {
            $mailer->AddAddress( $recipient );
        }

        $mailer->CharSet    = 'utf-8';                                   // Set the email character set
        $mailer->WordWrap   = 80;                                        // Set word wrap to 80 characters
        $mailer->IsHTML( true );                                         // Set email format to HTML

        $mailer->Subject    = $subject;
        $mailer->Body       = $html;
        $mailer->AltBody    = $text;

        if( substr( $mailer->From, -11, 11 ) === '@pinaxo.com' )
        {
            $mailer->DKIM_domain  = 'pinaxo.com';
            $mailer->DKIM_private = "/Users/administrator/.keys/dkim_rsa";
            $mailer->DKIM_selector= 'default';
            $mailer->DKIM_passphrase = '';
            $mailer->DKIM_identity= $mailer->From;
        }

        $mailer->Send();
    }
    catch (Exception $e)
    {
        $error = $mailer->ErrorInfo;
    }

    unset( $mailer );

    return $error;
}



function WorkerLogDB( $type, $message, $document_id )
{
    $error = "";

    $class = WorkerLogClassForType( $type );

    if( ! $document_id )
    {
        $document_id = 0;
    }

    QueryExecute( "log.sql", $error, array( "document_id" => $document_id, "class" => $class, "message" => $message ) );

    return $error;
}



function WorkerLogClassForType( $type )
{
    switch( $type )
    {
        case WORKER_INFO:
            $text = 'info';
            break;

        case WORKER_NOTICE:
            $text = 'notice';
            break;

        case WORKER_WARNING:
            $text = 'warning';
            break;

        case WORKER_ERROR:
            $text = 'error';
            break;

        default:
            $text = 'unknown';
            break;
    }

    return $text;
}



function WorkerLogPreambleForType( $type )
{
    switch( $type )
    {
        case WORKER_INFO:
            $text = '';
            break;

        case WORKER_NOTICE:
            $text = 'Notice: ';
            break;

        case WORKER_WARNING:
            $text = 'Warning: ';
            break;

        case WORKER_ERROR:
            $text = 'Error: ';
            break;

        default:
            $text = '';
            break;
    }

    return $text;
}


function WorkerLogSubjectForType( $type )
{
    switch( $type )
    {
        case WORKER_INFO:
            $text = WORKER_PROCESS . '';
            break;

        case WORKER_NOTICE:
            $text = WORKER_PROCESS . ': Notice';
            break;

        case WORKER_WARNING:
            $text = WORKER_PROCESS . ': WARNING';
            break;

        case WORKER_ERROR:
            $text = WORKER_PROCESS . ': ERROR';
            break;

        default:
            $text = WORKER_PROCESS . '';
            break;
    }

    return $text;
}