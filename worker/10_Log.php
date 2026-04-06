<?php

/*
 *
 *
 *  Log
 *
 *
 */


/*
 *
 *  GLOBALS
 *
 */

$gCurrentDayNumber = '0';
$gEmailCountToday = 0;



/*
 *
 *  CONSTANTS
 *
 */

define( 'WORKER_INFO',          1 );
define( 'WORKER_NOTICE',        2 );
define( 'WORKER_WARNING',       3 );
define( 'WORKER_ERROR',         4 );



/*
 *
 *  Log information, warning or error via db / email / terminal
 *  If document id doesn't apply pass zero or NULL
 *
 */

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
    $html = WorkerEmailThemeApply( $html );

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
        $mailer->Timeout    = 15;
        $mailer->getSMTPInstance()->Timelimit = 15;

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



function WorkerEmailThemeApply( $body )
{
    if( ! is_string( $body ) || trim( $body ) === '' )
    {
        return $body;
        /*--- EXIT POINT ---*/
    }

    $body = WorkerEmailThemeNormalizeText( $body );
    $body = WorkerEmailThemeNormalizeRed( $body );
    $body = WorkerEmailThemeNormalizeLinks( $body );

    $wrapperOpen = "<table width='100%' cellpadding='0' cellspacing='0' role='presentation' style='background-color:#000000;'><tr><td align='center' style='padding:24px;'><table width='100%' cellpadding='0' cellspacing='0' role='presentation' style='max-width:600px;'><tr><td style='color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:18px;line-height:1.6;text-align:left;-webkit-text-size-adjust:100%;'>";
    $wrapperClose = "</td></tr></table></td></tr></table>";

    if( stripos( $body, '<body' ) !== false )
    {
        $body = preg_replace_callback( '/<body\b([^>]*)>/i', function( $matches )
        {
            $attrs = $matches[1];
            $style = '';

            if( preg_match( '/\sstyle\s*=\s*([\'"])(.*?)\1/i', $attrs, $styleMatch ) )
            {
                $style = trim( $styleMatch[2] );
                $attrs = preg_replace( '/\sstyle\s*=\s*([\'"]).*?\1/i', '', $attrs, 1 );
            }

            $attrs = preg_replace( '/\sbgcolor\s*=\s*([\'"]).*?\1/i', '', $attrs, 1 );

            $style = trim( trim( $style, " ;\t\n\r\0\x0B" ) . '; margin:0; padding:0; background-color:#000000; color:#ffffff;' );

            return "<body{$attrs} bgcolor='#000000' style='{$style}'>";
        }, $body, 1 );

        $body = preg_replace( '/<body\b[^>]*>/i', '$0' . $wrapperOpen, $body, 1 );

        if( preg_match( '/<\/body>/i', $body ) )
        {
            $body = preg_replace( '/<\/body>/i', $wrapperClose . '</body>', $body, 1 );
        }
        else
        {
            $body .= $wrapperClose;
        }

        return $body;
        /*--- EXIT POINT ---*/
    }

    return "<body bgcolor='#000000' style='margin:0;padding:0;background-color:#000000;color:#ffffff;'>{$wrapperOpen}{$body}{$wrapperClose}</body>";
}


function WorkerEmailThemeNormalizeLinks( $body )
{
    return preg_replace_callback( '/<a\b([^>]*)>/i', function( $matches )
    {
        $attrs = $matches[1];

        if( preg_match( '/\sstyle\s*=\s*([\'"])(.*?)\1/i', $attrs, $styleMatch ) )
        {
            $quote = $styleMatch[1];
            $style = trim( $styleMatch[2] );

            if( preg_match( '/(^|;)\s*color\s*:/i', $style ) )
            {
                $style = preg_replace( '/(^|;)\s*color\s*:\s*[^;]+/i', '$1 color: #85b6ff', $style );
            }
            else
            {
                $style .= ( $style === '' ? '' : '; ' ) . 'color: #85b6ff';
            }

            $attrs = preg_replace( '/\sstyle\s*=\s*([\'"]).*?\1/i', '', $attrs, 1 );

            return "<a{$attrs} style={$quote}{$style}{$quote}>";
        }

        return "<a{$attrs} style='color: #85b6ff;'>";
    }, $body );
}


function WorkerEmailThemeNormalizeRed( $body )
{
    $body = preg_replace( '/color\s*:\s*(?:red|#f00|#ff0000|#b00|#bb0000)\b/i', 'color: #f84242', $body );
    $body = preg_replace( '/(<font\b[^>]*\bcolor\s*=\s*[\'"])\s*(?:red|#f00|#ff0000|#b00|#bb0000)\s*([\'"])/i', '$1#f84242$2', $body );

    return $body;
}


function WorkerEmailThemeNormalizeText( $body )
{
    $body = preg_replace( '/color\s*:\s*(?:black|#000|#000000|#111|#111111|#222|#222222|#333|#333333|#444|#444444|#555|#555555|#666|#666666|#777|#777777|#888|#888888|#999|#999999)\b/i', 'color: #ffffff', $body );
    $body = preg_replace( '/(<font\b[^>]*\bcolor\s*=\s*[\'"])\s*(?:black|#000|#000000|#111|#111111|#222|#222222|#333|#333333|#444|#444444|#555|#555555|#666|#666666|#777|#777777|#888|#888888|#999|#999999)\s*([\'"])/i', '$1#ffffff$2', $body );

    return $body;
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
