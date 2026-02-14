<?php

//
//
// Notify of notes on expired documents
//
//

function ExpiredNotes()
{
    $error = '';
    $query = '80_expired_notes.sql';
    $result = QueryExecute( $query, $error );

    if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }

    $n = count( $result );

    if( $n === 0 )
    {
        return;
        /*--- EXIT POINT ---*/
    }

    $milliseconds = Milliseconds();

    $config = [
        'host' => WORKER_EMAIL_HOST,
        'auth' => WORKER_EMAIL_AUTH,
        'user' => WORKER_EMAIL_USER,
        'pass' => WORKER_EMAIL_PASS,
        'encr' => WORKER_EMAIL_SCRE,
        'port' => WORKER_EMAIL_PORT
    ];

    $i = 1;
    foreach( $result as $r )
    {
        WorkerLog( WORKER_INFO, "Sending email $i:$n to {$r['email']} for a expired note", 0, true, false, 1 );
        $to = "{$r['fullname']} <{$r['email']}>";
        $subject = "Pinaxo | Note su listino scaduto di {$r['brand']}";
        $body = ExpiredNotesMakeBody( $r, $altBody );
        $body = WorkerEmailThemeApply( $body );
        $from = "Pinaxo Server <server@pinaxo.com>";

        $success = MailerSend( $config, $subject, $body, $altBody, $from, $to );

        if( ! $success )
        {
            $error = MailerError();
            WorkerLog( WORKER_WARNING, "Expired notes - mailing to {$r['email']} failed, error: $error", 0, true, true, true );
        }

        sleep(1);

        WorkerAlive();

        $i++;
    }

    $error = '';
    $query = '81_expired_notes_update.sql';
    $result = QueryExecute( $query, $error );

    if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Sent notifications for $n expired notes: $milliseconds ms", 0, false, false, 1 );
    sleep(3);
}



function ExpiredNotesMakeBody( $r, &$altBody )
{
    $html = "<img src='https://www.pinaxo.com/cover/{$r['public_id']}/472px' style='border: 1px solid #000;' alt='Copertina documento'><br>\n<br>\n<hr><br>\n<br>\n".
        "Buongiorno {$r['firstname']}<br>\n".
        "&nbsp;&nbsp;&nbsp;&nbsp;ti scriviamo per notificarti che il documento<br>\n<br>\n".
        "<b>{$r['brand']}</b> - <b>{$r['description']}</b><br>\n<br>\n".
        "riporta un'annotazione da te inserita.<br>\n".
        "<b>Il listino &egrave; scaduto</b> pertanto la nota<br>\n".
        "non &egrave; pi&ugrave; visibile ai tuoi collaboratori<br>\n".
        "a meno di fare click su 'Visualizza listini scaduti'.<br>\n<br>\n".
        "Il testo della nota &egrave; riportato in fondo al messaggio.<br>\n<br>\n".
        "Per accedere alla pagina di <b>{$r['brand']}</b> fai click <b><a href='https://www.pinaxo.com/it/reserved/{$r['category_id']}/{$r['brand_id']}'>qui</a></b>.<br>\n<br>\n".
        "Questo messaggio &egrave; generato automaticamente<br>\n".
        "ma puoi rispondere a questa email in caso di necessit&agrave;,<br>\n".
        "il nostro staff ricever&agrave; la tua richiesta.<br>\n<br>\n<hr><br>\n<br>\n".
        "{$r['notes']}<br>\n<br>\n<hr><br>\n<br>\n";

    $altBody = $html;

    while( true )
    {
        $tag = StringBetween( $altBody, "<", ">" );
        if( $tag === false )
        {
            break;
        }
        $altBody = StringReplace( $altBody, "<$tag>", "" );
    }

    $altBody = StringReplace( $altBody, "&nbsp;", " " );

    $altBody = html_entity_decode( $altBody, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8' );

    return $html;
}
