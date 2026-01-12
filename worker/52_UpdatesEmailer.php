<?php

//
//
// Notify the imminent mailing of price list updates
//
//

function UpdatesNotify()
{
    $today = date( "Y-m-d" );

    $error = '';
    $query = '82_updates_notify.sql';
    $result = QueryExecute( $query, $error, [ 'when' => $today ] );

    if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }

    $n = count( $result );

    if( $n === 0 )
    {
        return;
        /*--- EXIT POINT ---*/
    }

    $id = $result[0]['id'];
    $from = $result[0]['from'];

    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Sending updates notice...", 0, false, false, 1 );

    $output = FSExecute( [ '/usr/local/bin/php', '/Users/administrator/Scripts/Php/Newsletter/updates.php', '--list', $from ], $status );

    if( $status != 0 )
    {
        WorkerLog( WORKER_ERROR, "FATAL - php updates.php --list $from failed - Error: $output", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $recipients_count = substr_count( $output, "\n" ) - 3;

    $output = "Tomorrow and day after tomorrow price list updates will be sent to the recipients listed below\n\n$output\n\n$recipients_count recipients\n\n";

    $text = $output;
    $html = StringReplace( $output, "\n", "<br>\n" );
    $html = StringReplace( $html, " ", "&nbsp;" );
    $html = "<span style='font-family: courier; font-size: 13px'>\n$html</span>";

    $config = [
        'host' => WORKER_EMAIL_HOST,
        'auth' => WORKER_EMAIL_AUTH,
        'user' => WORKER_EMAIL_USER,
        'pass' => WORKER_EMAIL_PASS,
        'encr' => WORKER_EMAIL_SCRE,
        'port' => WORKER_EMAIL_PORT
    ];

    $to = "paolo.bertani@me.com";
    $subject = "Pinaxo | Updates mailing notice";
    $from = "Pinaxo Server <server@pinaxo.com>";

    $when_sent = date( 'Y-m-d H:i:s' );

    $success = MailerSend( $config, $subject, $html, $text, $from, $to );

    if( ! $success )
    {
        $error = MailerError();
        WorkerLog( WORKER_WARNING, "Updates mailing notice - mailing to $to failed, error: $error", 0, true, true, true );
    }

    $milliseconds = Milliseconds( $milliseconds );
    $elapsed_time = intval( $milliseconds / 1000 );

    $error = '';
    $query = '83_updates_update.sql';
    $result = QueryExecute( $query, $error, [ 'id' => $id, 'when_sent' => $when_sent, 'recipients_count' => 1, 'elapsed_time' => $elapsed_time ] );

    if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }

    WorkerLog( WORKER_INFO, "Sent updates notice: $milliseconds ms", 0, false, false, 1 );
    sleep(3);
}



//
//
// Send the price list updates to the mailing list
//
//

function UpdatesSend()
{
    $today = date( "Y-m-d" );

    $error = '';
    $query = '84_updates_send.sql';
    $result = QueryExecute( $query, $error, [ 'when' => $today ] );

    if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }

    $n = count( $result );

    if( $n === 0 )
    {
        return;
        /*--- EXIT POINT ---*/
    }

    $id = $result[0]['id'];
    $from = $result[0]['from'];
    $action = $result[0]['action'];

    $list_action = '';
    if( $action === 'send' ) $list_action = 'list';
    if( $action === 'snd0' ) $list_action = 'lst0';
    if( $action === 'snd1' ) $list_action = 'lst1';

    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Sending updates emails [$action] to mailing list...", 0, false, false, 1 );

    $output = FSExecute( [ '/usr/local/bin/php', '/Users/administrator/Scripts/Php/Newsletter/updates.php', "--$list_action", $from ], $status );

    if( $status != 0 )
    {
        WorkerLog( WORKER_ERROR, "FATAL - php updates.php --$list_action $from failed - Error: $output", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $recipients_count = substr_count( $output, "\n" ) - 3;

    $when_sent = date( 'Y-m-d H:i:s' );

    $output = FSExecute( [ '/usr/local/bin/php', '/Users/administrator/Scripts/Php/Newsletter/updates.php', "--$action", $from ], $status );

    if( $status != 0 )
    {
        WorkerLog( WORKER_ERROR, "FATAL - php updates.php --$action $from failed - Error: $output", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $elapsed_time = intval( Milliseconds( $milliseconds ) / 1000 );

    $error = '';
    $query = '83_updates_update.sql';
    $result = QueryExecute( $query, $error, [ 'id' => $id, 'when_sent' => $when_sent, 'recipients_count' => $recipients_count, 'elapsed_time' => $elapsed_time ] );

    if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }

    $text = $output;
    $html = StringReplace( $output, "\n", "<br>\n" );
    $html = StringReplace( $html, " ", "&nbsp;" );
    $html = "<span style='font-family: courier; font-size: 13px'>\n$html</span>";

    $config = [
        'host' => WORKER_EMAIL_HOST,
        'auth' => WORKER_EMAIL_AUTH,
        'user' => WORKER_EMAIL_USER,
        'pass' => WORKER_EMAIL_PASS,
        'encr' => WORKER_EMAIL_SCRE,
        'port' => WORKER_EMAIL_PORT
    ];

    $to = "paolo.bertani@me.com";
    $subject = "Pinaxo | Sent updates email to mailing list [$action]";
    $from = "Pinaxo Server <server@pinaxo.com>";

    $success = MailerSend( $config, $subject, $html, $text, $from, $to );

    if( ! $success )
    {
        $error = MailerError();
        WorkerLog( WORKER_WARNING, "Updates mailing send - mailing to $to failed, error: $error", 0, true, true, true );
    }

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Sent updates emails [$action] to mailing list: $milliseconds ms", 0, true, false, 1 );
    sleep(3);
}


