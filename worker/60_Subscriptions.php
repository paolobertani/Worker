<?php

//
//
// Subscriptions
//
//

function Subscriptions()
{
    SubscriptionsCCSuspendExpired();

    SubscriptionsBTSuspendExpired();

    SubscriptionsIssuePaymentMaybe();
}



//
// SubscriptionsCCSuspendExpired
//

function SubscriptionsCCSuspendExpired()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Suspending expired credit-card subscriptions...", 0, false, false, 1 );

    $count = 0;

    while( true )
    {
        $today = date( 'Y-m-d' );

        $error = '';
        $result = QueryExecute( 'A1_subscriptions_expired.sql', $error, [ 'today' => $today ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - A1_subscriptions_expired.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }

        if( count( $result ) === 0 )
        {
            break;
        }

        $subscription_id = $result[ 0 ][ 'id' ];
        $description = $result[ 0 ][ 'description' ];
        $count++;

        $error = '';
        $result = QueryExecute( 'A2_subscriptions_deactivate.sql', $error, [ 'id' => $subscription_id ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - A1_subscriptions_expired.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }

        WorkerLog( WORKER_INFO, "Deactivated expired subscription: $description", 0, true, true, 1 );
        sleep(1);

        SubscriptionSetGroup( $subscription_id, NEXI_EMPTY_GROUP_ID );
    }

    $logdb = $count > 0 ? true : false;
    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Deactivated $count expired credit-card subscriptions: $milliseconds ms", 0, $logdb, false, 1 );
    WorkerAlive();
    sleep(3);
}



//
// SubscriptionsBTSuspendExpired
//

function SubscriptionsBTSuspendExpired()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Suspending expired bank-transfer subscriptions...", 0, false, false, 1 );

    $count = 0;

    while( true )
    {
        $today = date( 'Y-m-d' );

        $error = '';
        $result = QueryExecute( 'A1bsubscriptions_expired.sql', $error, [ 'today' => $today ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - A1bsubscriptions_expired.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }

        if( count( $result ) === 0 )
        {
            break;
        }

        $subscription_id = $result[ 0 ][ 'id' ];
        $description = $result[ 0 ][ 'description' ];
        $count++;

        $error = '';
        $result = QueryExecute( 'A2bsubscriptions_deactivate.sql', $error, [ 'id' => $subscription_id ] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - A2bsubscriptions_deactivate.sql: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }

        WorkerLog( WORKER_INFO, "Deactivated expired subscription: $description", 0, true, true, 1 );
        sleep(1);

        SubscriptionSetGroup( $subscription_id, NEXI_EMPTY_GROUP_ID );

        SubscriptionSendMailAsBankTransferIsExpected( $result[ 0 ] );
    }

    $logdb = $count > 0 ? true : false;
    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Deactivated $count expired bank-transfer subscriptions: $milliseconds ms", 0, $logdb, false, 1 );
    WorkerAlive();
    sleep(3);
}



//
// SubscriptionSetGroup
//

function SubscriptionSetGroup( $subscription_id, $group_id )
{
    if( $subscription_id == 0 )
    {
        WorkerLog( WORKER_ERROR, "FATAL - A3_subscriptions_set_group.sql: subscription_id cannot be 0 - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $error = '';
    $result = QueryExecute( 'A3_subscriptions_set_group.sql', $error, [ 'subscription_id' => $subscription_id, 'group_id' => $group_id ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - A3_subscriptions_set_group.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



//
// SubscriptionsIssuePaymentMaybe
//

function SubscriptionsIssuePaymentMaybe()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Issue payments...", 0, false, false, 1 );

    // Payment to be issued

    $today = date( 'Y-m-d' );

    $error = '';
    $result = QueryExecute( 'A0_subscriptions_payment_to_issue.sql', $error, [ 'today' => $today ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - A0_subscriptions_payment_to_issue.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( count( $result ) === 0 )
    {
        $milliseconds = Milliseconds( $milliseconds );
        WorkerLog( WORKER_INFO, "No payments to issue: $milliseconds ms", 0, false, false, 1 );
        WorkerAlive();
        sleep(3);
        return;
        /*--- EXIT POINT ---*/
    }

    $subscription =    $result[0];

    $id =              $subscription[ 'id' ];
    $description =     $subscription[ 'description' ];
    $valid_until =     $subscription[ 'valid_until' ];
    $valid_until_day = $subscription[ 'valid_until_day' ];
    $amount =          $subscription[ 'amount' ];
    $duration =        $subscription[ 'duration' ];
    $vat =             $subscription[ 'vat' ];

    $total = round( $duration * $amount * ( 1 + $vat / 100 ), 2 );

    $mail_error = '';
    $success = SubscriptionsCashIn( $subscription, $mail_error ); // payments record is created here

    if( $success )
    {
        $valid_until = SubscriptionsGetValidUntil( $valid_until_day, $valid_until, $duration );
        $last_payment_did_fail = 0;
        $is_active = 1;
        $payment_is_auto = 1;
        $payment_request = "PP"; // Payment request remains 'Primo pagamento'

        $message = "Issued payment `$description` $total euro: $milliseconds ms";
    }
    else
    {
        // $valid_until not changed
        $last_payment_did_fail = 1;
        $is_active = 0;
        $payment_is_auto = 0;
		/* NO! what if the problem is due to inefficient funds? *
		$payment_request = "RC"; // Switch to 'Rinnovo carta' as it is assumed that the card will be changed
		********/ $payment_request = "PP";
        $message = "FAILED payment `$description` $total euro: $milliseconds ms";

        $subscription_id = $id;
        SubscriptionSetGroup( $subscription_id, NEXI_EMPTY_GROUP_ID );
    }

    $error = '';
    $result = QueryExecute( 'A4_subscriptions_update.sql', $error, [
        'id' => $id,
        'valid_until' => $valid_until,
        'last_payment_did_fail' => $last_payment_did_fail,
        'is_active' => $is_active,
        'payment_is_auto' => $payment_is_auto,
        'payment_request' => $payment_request
    ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - A4_subscriptions_update.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( $mail_error !== '' )
    {
        $mail_error .= ( $result ? ' - payment ok' : ' - payment failed' );
        WorkerLog( WORKER_ERROR, $mail_error, 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Issued payment `$description` $total euro: $milliseconds ms", 0, true, false, 1 );
    WorkerAlive();
    sleep(3);
}



//
// SubscriptionsCashIn
//

function SubscriptionsCashIn( $subscription, &$mail_error )
{
    $subscription_id = $subscription[ 'id' ];

    $error = '';
    $result = QueryExecute( 'A5_get_payments_count.sql', $error, [ 'subscription_id' => $subscription_id ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - A5_get_payments_count.sql - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( count( $result ) === 1 && isset( $result[ 0 ][ 'count' ] ) )
    {
        $payment_id = $result[ 0 ][ 'count' ] + 1;
    }
    else
    {
        $payment_id = "?";
    }

    $agreement_id =     $subscription[ 'agreement_id' ];
    $subscription_id =  $subscription[ 'id' ];
    $pan_expire =       $subscription[ 'pan_expire' ];
    $amount =           $subscription[ 'amount' ];
    $duration =         $subscription[ 'duration' ];
    $vat =              $subscription[ 'vat' ];
    $user_id =          $subscription[ 'user_id' ];
    $group_id =         $subscription[ 'group_id' ];
    $subscription_description = $subscription[ 'description' ];
    $valid_until_day =  $subscription[ 'valid_until_day' ];
    $valid_until =      $subscription[ 'valid_until' ];

    $from =             date( "Y-m-d" );
    $to =               SubscriptionsGetValidUntil( $valid_until_day, $valid_until, $duration );
    $pan =              $subscription[ 'pan' ];
    $nexi_id =          NEXI_ALIAS_RECURR;
    $nexi_key =         '*' . substr( NEXI_KEY_RECURR, -4, 4 );

    $amount_nexi =      round( $duration * $amount * ( 1 + $vat / 100 ), 2 );

    $requestUrl =       NEXI_URL_RECURR;
    $alias =            NEXI_ALIAS_RECURR;
    $secret =           NEXI_KEY_RECURR;
    $numContratto =     $agreement_id;
    $when =             date( 'Y-m-d H:i:s', time() );
    $transaction_id =   "PINAXO SUBS {$subscription_id} TRNS {$payment_id}";
    $importo =          intval( round( $amount_nexi * 100 ) );
    $divisa =           '978'; // EUR
    $timeStamp =        time() * 1000;
    $scadenza =         ''; // $pan_expire;
    $mac = sha1(
        'apiKey=' . $alias .
        'numeroContratto=' . $numContratto .
        'codiceTransazione=' . $transaction_id .
        'importo=' . $importo .
        "divisa=" . $divisa .
        "scadenza=" . $scadenza .
        "timeStamp=" . $timeStamp .
        $secret
    );

    $requestParams = [
        'apiKey' =>             $alias,
        'numeroContratto' =>    $numContratto,
        'codiceTransazione' =>  $transaction_id,
        'importo' =>            "$importo",
        'divisa' =>             "$divisa",
        'timeStamp' =>          "$timeStamp",
        'mac' =>                $mac
    ];

    $json = json_encode( $requestParams );

    $connection = curl_init();
    if( ! $connection )
    {
        WorkerLog( WORKER_ERROR, "FATAL - Subscriptions: cannot init CURL", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    curl_setopt( $connection, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
    curl_setopt( $connection, CURLOPT_URL, $requestUrl );
    curl_setopt( $connection, CURLOPT_POST, 1 );
    curl_setopt( $connection, CURLOPT_POSTFIELDS, $json );
    curl_setopt( $connection, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $connection, CURLINFO_HEADER_OUT, true );
    curl_setopt( $connection, CURLOPT_SSL_VERIFYHOST,   0 );
    curl_setopt( $connection, CURLOPT_SSL_VERIFYPEER,   0 );

    $response = curl_exec( $connection );

    $errnum = curl_errno( $connection );

    if( $errnum != 0 )
    {
        WorkerLog( WORKER_ERROR, "FATAL - Subscriptions: CURL failed with error $errnum", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $status = curl_getinfo( $connection, CURLINFO_HTTP_CODE );

    if( $status < 200 || $status > 399 )
    {
        WorkerLog( WORKER_ERROR, "FATAL - Subscriptions: CURL failed with status $errnum", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    //curl_close( $connection );

    $response = json_decode( $response, true );

    if( $response === null )
    {
        WorkerLog( WORKER_ERROR, "FATAL - Subscriptions: CURL cannot decode JSON response", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $ok = true;
    $message = '';

    if( $response['esito'] === "OK" )
    {
        $macCalculated = sha1( 'esito=' . $response['esito'] . 'idOperazione=' . $response['idOperazione'] . 'timeStamp=' . $response['timeStamp'] . $secret );
        if( $macCalculated !== $response['mac'] )
        {
            WorkerLog( WORKER_ERROR, "FATAL - Subscriptions: CURL failed returning MAC value mismatch", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
		$message = '';
    }
    else
    {
        $ok = false;
        $message = trim( $response['errore']['messaggio'] ?? '' );
    }

    $error = '';
    $result = QueryExecute( 'A5_subscriptions_insert_payment.sql', $error, [
        'subscription_id' => $subscription_id,
        'duration' => $duration,
        'amount' => $amount,
        'vat' => $vat,
        'when' => $when,
        'status' => ( $ok ? 1 : 0 ),
        'amount_nexi' => $amount_nexi,
        'transaction_id' => $transaction_id,
        'from' => $from,
        'to' => $to,
        'pan' => $pan,
        'nexi_id' => $nexi_id,
        'nexi_key' => $nexi_key,
		'nexi_message' => $message
    ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - A5_subscriptions_insert_payment.sql - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $error = '';
    $result = QueryExecute( 'A6_get_user.sql', $error, [ 'user_id' => $user_id ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - A6_get_user.sql - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $username =     $result[ 0 ][ 'username' ];
    $firstname =    $result[ 0 ][ 'firstname' ];
    $surname =      $result[ 0 ][ 'surname' ];
    $email =        $result[ 0 ][ 'email' ];
    $lang =         $result[ 0 ][ 'lang' ];

    $error = '';
    $result = QueryExecute( 'A7_get_group.sql', $error, [ 'group_id' => $group_id ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - A7_get_group.sql - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $group = $result[ 0 ][ 'group' ];

    if( $ok )
    {
        $valid_until = SubscriptionsGetValidUntil( $valid_until_day, $valid_until, $duration );
    }

    if( $ok )
    {
        $subject = "Payment received | ";
        $body = "<br>\n<br>\n<b>Payment received</b>";
    }
    else
    {
        $subject = "Payment failed | ";
        $body = "<br>\n<br>\n<span style='color: #b00;'><b>Payment failed</b></span>";
    }

    $subject .= $subscription_description;
    $altBody = $subject;

    if( ! NEXI_PRODUCTION ) $subject .= " | TESTING";

    $body .= "<br>\n<br>\n<hr><br>\n<br>\n";
    $body .= "<b>Subscription:</b>&nbsp;$subscription_description<br>\n<br>\n";
    $body .= "<b>Subs. Id:</b>&nbsp;$subscription_id<br>\n<br>\n";
    $body .= "<b>Agreement Id:</b>&nbsp;$numContratto<br>\n<br>\n";
    $body .= "<b>User:</b>&nbsp;$firstname $surname [$username]<br>\n<br>\n";
    $body .= "<b>Email:</b>&nbsp;$email<br>\n<br>\n";
    $body .= "<b>Group:</b>&nbsp;$group<br>\n<br>\n";
    $body .= "<b>Valid until:</b>&nbsp;$valid_until<br>\n<br>\n";
    $body .= "<hr><br>\n<br>\n";
    $body .= "<b>Transaction Id:</b>&nbsp;$transaction_id<br>\n<br>\n";
    $body .= "<b>Issued:</b>&nbsp;automatically<br>\n<br>\n";
    $body .= "<b>Duration:</b>&nbsp;$duration month" . ( $duration > 1 ? "s" : "" ) . "<br>\n<br>\n";
    $body .= "<b>Amount:</b>&nbsp;$amount euro<br>\n<br>\n";
    $body .= "<b>VAT:</b>&nbsp;$vat<br>\n<br>\n";
    $body .= "<b>Total:</b>&nbsp;$amount_nexi<br>\n<br>\n";
    $body .= "<b>Date and time:</b>&nbsp;$when<br>\n<br>\n";
    if( $message != '' ) $body .= "<b>Nexi Message:</b>&nbsp;$message<br>\n<br>\n";
    $body .= "<hr><br>\n<br>\n&nbsp;";

    $mailer = new PHPMailer\PHPMailer\PHPMailer( false );

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
    $mailer->AddAddress( 'payments@pinaxo.com' );

    $mailer->CharSet    = 'utf-8';                                   // Set the email character set
    $mailer->WordWrap   = 80;                                        // Set word wrap to 80 characters
    $mailer->IsHTML( true );                                         // Set email format to HTML

    $mailer->Subject    = $subject;
    $mailer->Body       = $body;
    $mailer->AltBody    = $altBody;

    if( substr( $mailer->From, -11, 11 ) === '@pinaxo.com' )
    {
        $mailer->DKIM_domain  = 'pinaxo.com';
        $mailer->DKIM_private = "/Users/administrator/.keys/dkim_rsa";
        $mailer->DKIM_selector= 'default';
        $mailer->DKIM_passphrase = '';
        $mailer->DKIM_identity= $mailer->From;
    }

    $result = $mailer->Send();

    unset( $mailer );

    if( ! $result )
    {
        $mail_error = "FATAL - Subscriptions - Failed sending email to payments@pinaxo.com - Error: {$mailer->ErrorInfo}";
    }

    sleep( 1 );

    // mail to customer

    if( $ok )
    {
        if( $lang === 'it' )
        {
            $subject = "Pinaxo | Pagamento ricevuto";
            $body = "<br>\n<br>\n<b>Pagamento ricevuto</b>";
        }
        else
        {
            $subject = "Pinaxo | Payment received";
            $body = "<br>\n<br>\n<b>Payment received</b>";
        }
    }
    else
    {
        if( $lang === 'it' )
        {
            $subject = "Pinaxo | Pagamento respinto";
            $body = "<br>\n<br>\n<span style='color: #b00;'><b>Pagamento respinto</b></span>";
        }
        else
        {
            $subject = "Pinaxo | Payment failed";
            $body = "<br>\n<br>\n<span style='color: #b00;'><b>Payment failed</b></span>";
        }
    }

    $altBody = $subject;

    if( ! NEXI_PRODUCTION ) $subject .= " | TESTING";

    if( $lang === 'it' )
    {
        $plural = $duration > 1 ? "i" : "e";
        $body .= "<br>\n<br>\n<hr><br>\n<br>\n";
        $body .= "<b>Utente riferimento:</b>&nbsp;$firstname $surname [$username]<br>\n<br>\n";
        $body .= "<b>Email:</b>&nbsp;$email<br>\n<br>\n";
        $body .= "<b>Abbonamento valido fino al:</b>&nbsp;$valid_until<br>\n<br>\n";
        $body .= "<hr><br>\n<br>\n";
        $body .= "<b>Durata:</b>&nbsp;$duration mes$plural<br>\n<br>\n";
        $body .= "<b>Imponibile:</b>&nbsp;$amount euro x $duration mes$plural = " . ( $duration * $amount ) . " euro<br>\n<br>\n";
        $body .= "<b>IVA:</b>&nbsp;$vat<br>\n<br>\n";
        $body .= "<b>Totale:</b>&nbsp;$amount_nexi<br>\n<br>\n";
        $body .= "<b>Data e ora:</b>&nbsp;$when<br>\n<br>\n";
    }
    else
    {
        $plural = $duration > 1 ? "s" : "";
        $body .= "<br>\n<br>\n<hr><br>\n<br>\n";
        $body .= "<b>Subscribed user:</b>&nbsp;$firstname $surname [$username]<br>\n<br>\n";
        $body .= "<b>Email:</b>&nbsp;$email<br>\n<br>\n";
        $body .= "<b>Subscription valid until:</b>&nbsp;$valid_until<br>\n<br>\n";
        $body .= "<hr><br>\n<br>\n";
        $body .= "<b>Duration:</b>&nbsp;$duration month$plural<br>\n<br>\n";
        $body .= "<b>Amount:</b>&nbsp;$amount euro x $duration month$plural = " . ( $duration * $amount ) . " euro<br>\n<br>\n";
        $body .= "<b>VAT/TAX:</b>&nbsp;$vat<br>\n<br>\n";
        $body .= "<b>Amount due:</b>&nbsp;$amount_nexi<br>\n<br>\n";
        $body .= "<b>Date and time:</b>&nbsp;$when<br>\n<br>\n";
    }

    if( ! $ok )
    {
        if( $lang === 'it' )
        {
            $body .= "<hr><br>\n<br>\n";
            $body .= "Entra nell'area riservata di <b>Pinaxo</b><br>\n";
            $body .= "e accedi ad <b>Abbonamento</b> dal menu in alto a sinistra.<br>\n";
            $body .= "Successivamente ritenta il pagamento manualmente.<br>\n<br>\n";
            $body .= "Per ricevere assistenza scrivi a <b>support@pinaxo.com</b><br>\n<br>\n";
        }
        else
        {
            $body .= "<hr><br>\n<br>\n";
            $body .= "Please, enter <b>Pinaxo</b> reserved area with your username and password<br>\n";
            $body .= "and select <b>Subscription</b> from the top-left menu.<br>\n";
            $body .= "Then retry the payment manually. Maybe you card is expired.<br>\n<br>\n";
            $body .= "To receive assistance write us to <b>support@pinaxo.com</b><br>\n<br>\n";
        }
    }

    if( $lang === 'it' )
    {
        $body .= "<hr><br>\n<br>\n";
        $body .= "Grazie!<br>\n<br>\n";
        $body .= "<hr><br>\n<br>\n&nbsp;";
    }
    else
    {
        $body .= "<hr><br>\n<br>\n";
        $body .= "Thank you!<br>\n<br>\n";
        $body .= "<hr><br>\n<br>\n&nbsp;";
    }

    $mailer = new PHPMailer\PHPMailer\PHPMailer( false );

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
    $mailer->FromName   = "Pinaxo Server";
    $mailer->AddAddress( $email, "$firstname $surname" );
    /**/ $mailer->addBCC( "paolo.bertani@me.com", "Paolo Bertani" );

    $mailer->CharSet    = 'utf-8';                                   // Set the email character set
    $mailer->WordWrap   = 80;                                        // Set word wrap to 80 characters
    $mailer->IsHTML( true );                                         // Set email format to HTML

    $mailer->Subject    = $subject;
    $mailer->Body       = $body;
    $mailer->AltBody    = $altBody;

    if( substr( $mailer->From, -11, 11 ) === '@pinaxo.com' )
    {
        $mailer->DKIM_domain  = 'pinaxo.com';
        $mailer->DKIM_private = "/Users/administrator/.keys/dkim_rsa";
        $mailer->DKIM_selector= 'default';
        $mailer->DKIM_passphrase = '';
        $mailer->DKIM_identity= $mailer->From;
    }

    $result = $mailer->Send();

    unset( $mailer );

    if( ! $result )
    {
        if( $mail_error === '' )
        {
            $mail_error = "FATAL - Subscriptions - Failed sending email to $email - Error: {$mailer->ErrorInfo}";
        }
        else
        {
            $mail_error = "FATAL - Subscriptions - Failed sending email to payments@pinaxo.com and $email";
        }
    }

    return $ok;
}



//
// One month later on the specified day [from controllers/subscriptions.php]
//

function SubscriptionsGetValidUntil( $valid_until_day, $valid_until, $duration )
{
    $time = strtotime( $valid_until );

    $year = intval( date( 'Y', $time ) );
    $month = intval( date( 'n', $time ) );

    $daysPerMonth = [
     	0,
     	31,
     	28,
     	31,
     	30,
     	31,
     	30,
     	31,
     	31,
     	30,
     	31,
     	30,
     	31
    ];

    $month += $duration;
    if( $month > 12 )
    {
        $month -= 12;
        $year++;
    }

    $day = min( $valid_until_day, $daysPerMonth[ $month ] );

    return date( 'Y-m-d', strtotime( "$year-$month-$day" ) );
}



//
// Send email to main admins as a `bt` subscription is expired so an incoming bank transfer is expected
//

function SubscriptionSendMailAsBankTransferIsExpected( $subscription )
{
    $from = $subscription[ 'description' ];
    $amount = round( $subscription[ 'amount' ] * ( 100 + $subscription[ 'vat' ] ) / 100, 2 );

    WorkerLog( WORKER_INFO, "Expected incoming bank transfer from `$subscription", 0 );
}