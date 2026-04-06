<?php

/*
 *
 *
 *  Subscriptions
 *
 *
 */

function Subscriptions()
{
    SubscriptionsCCSuspendExpired();

    SubscriptionsBTSuspendExpired();

    SubscriptionsIssuePaymentMaybe();
}



/*
 *
 *  SubscriptionsCCSuspendExpired
 *
 */

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

        InvalidateCache( 'subscriptions' );

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



/*
 *
 *  SubscriptionsBTSuspendExpired
 *
 */

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

        InvalidateCache( 'subscriptions' );

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



/*
 *
 *  SubscriptionSetGroup
 *
 */

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

    InvalidateCache( 'users' );
}



/*
 *
 *  SubscriptionsIssuePaymentMaybe
 *
 */

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
    $agreement_id =    $subscription[ 'agreement_id' ];
    $description =     $subscription[ 'description' ];
    $valid_until =     $subscription[ 'valid_until' ];
    $valid_until_day = $subscription[ 'valid_until_day' ];
    $amount =          $subscription[ 'amount' ];
    $duration =        $subscription[ 'duration' ];
    $vat =             $subscription[ 'vat' ];

    $total = round( $duration * $amount * ( 1 + $vat / 100 ), 2 );

    $mail_error = '';
    $success = SubscriptionsCashIn( $subscription, $mail_error ); // payments record is created here - mails are sent here

    // The outcome of the cash in ( success VS fail (error or transaction denied) )

    if( $success )
    {
        $valid_until = SubscriptionsGetValidUntil( $valid_until_day, $valid_until, $duration );
        $last_payment_did_fail = 0;
        $is_active = 1;
        $payment_is_auto = 1;
        $payment_request = "PP";  // payment_request MUST stay `PP`

        $message = "Issued payment `$description` $total euro: $milliseconds ms";
    }
    else
    {
        /*
         *  FAIL:
         *  `valid_until` is not changed
         *  agreement id is regenerated
         *  group_id of subscribed user is set to INACTIVE_GROUP
         */

        $last_payment_did_fail = 1;
        $is_active = 0;
        $payment_is_auto = 0;
		$payment_request = "PP";  // payment_request MUST stay `PP`
        $message = "FAILED payment `$description` $total euro: $milliseconds ms";

        $subscription_id = $id;
        SubscriptionSetGroup( $subscription_id, NEXI_EMPTY_GROUP_ID );

        // When cash-in fails `agreement_id` must be updated

        if( substr( $agreement_id, 0, 5 ) === 'SUBSC' ) // legacy IDs
        {
            $agreement_id = "PINAXO SUBS {$subscription_id}.2";
        }
        else // new IDs
        {
            if( strpos( $agreement_id, '.' ) !== false )
            {
                // get and increment counter

                $parts = explode( '.', $agreement_id );
                $counter = intval( $parts[ 1 ] );
                $counter++;
                $agreement_id = "PINAXO SUBS {$subscription_id}.{$counter}";
            }
            else
            {
                $agreement_id = "PINAXO SUBS {$subscription_id}.2";
            }
        }
    }

    // Subscriprion is update accordingly to the cash in outcome

    $error = '';
    $result = QueryExecute( 'A4_subscriptions_update.sql', $error, [
        'id' => $id,
        'valid_until' => $valid_until,
        'last_payment_did_fail' => $last_payment_did_fail,
        'is_active' => $is_active,
        'payment_is_auto' => $payment_is_auto,
        'payment_request' => $payment_request,
        'agreement_id' => $agreement_id
    ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - A4_subscriptions_update.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'subscriptions' );

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



/*
 *
 *  SubscriptionsCashIn
 *
 */

function SubscriptionsCashIn( $subscription, &$mail_error )
{
    $subscription_id = $subscription[ 'id' ];

    // Get the payments count to compute new payment `id`

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

    // Get subscription data from the received arguement

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

    // This payment covers period from today to this month + duration on the specified day of month

    $from =             date( "Y-m-d" );
    $to =               SubscriptionsGetValidUntil( $valid_until_day, $valid_until, $duration );

    //

    $pan =              $subscription[ 'pan' ];                     // we assume the PAN is the same of the one saved on the last manual payment
    $nexi_id =          NEXI_ALIAS_RECURR;                          // our NEXI id
    $nexi_key =         '*' . substr( NEXI_KEY_RECURR, -4, 4 );     // we save the last 4 digits of the KEY

    $amount_nexi =      round( $duration * $amount * ( 1 + $vat / 100 ), 2 );

    $requestUrl =       NEXI_URL_RECURR;
    $alias =            NEXI_ALIAS_RECURR;
    $secret =           NEXI_KEY_RECURR;
    $numContratto =     $agreement_id;
    $when =             date( 'Y-m-d H:i:s', time() );
    $transaction_id =   "{$agreement_id} TRNS {$payment_id}";
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

    // Make the curl call to cash in

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

    // Payment successfully cashed in (ok=true) VS payment failed (ok=false)
    // $ok is returned back at the end

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

    // Payment is inserted with proper status

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
        'pan' => $pan ?? '?',
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

    InvalidateCache( 'payments' );

    // From now on we just send emails

    // Get user with some of its info (to later send email)

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

    // Get group

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
    $mailer->Timeout    = 15;
    $mailer->getSMTPInstance()->Timelimit = 15;

    $mailer->From       = WORKER_EMAIL_FROM;
    $mailer->FromName   = WORKER_EMAIL_NAME;
    $mailer->AddAddress( 'payments@pinaxo.com' );

    $mailer->CharSet    = 'utf-8';                                   // Set the email character set
    $mailer->WordWrap   = 80;                                        // Set word wrap to 80 characters
    $mailer->IsHTML( true );                                         // Set email format to HTML

    $body = WorkerEmailThemeApply( $body );

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

    $locale = SubscriptionsPaymentEmailLocale( $lang );

    if( $ok )
    {
        $subject = $locale[ 'subject_received' ];
        $body = "<br>\n<br>\n<b>{$locale['title_received']}</b>";
    }
    else
    {
        $subject = $locale[ 'subject_failed' ];
        $body = "<br>\n<br>\n<span style='color: #b00;'><b>{$locale['title_failed']}</b></span>";
    }

    $altBody = $subject;

    if( ! NEXI_PRODUCTION ) $subject .= " | TESTING";

    $month_word = $duration > 1 ? $locale[ 'month_plural' ] : $locale[ 'month_singular' ];
    $amount_text = str_replace(
        [ '{amount}', '{duration}', '{month_word}', '{subtotal}' ],
        [ $amount, $duration, $month_word, $duration * $amount ],
        $locale[ 'amount_formula' ]
    );

    $body .= "<br>\n<br>\n<hr><br>\n<br>\n";
    $body .= "<b>{$locale['label_user']}:</b>&nbsp;$firstname $surname [$username]<br>\n<br>\n";
    $body .= "<b>{$locale['label_email']}:</b>&nbsp;$email<br>\n<br>\n";
    $body .= "<b>{$locale['label_valid_until']}:</b>&nbsp;$valid_until<br>\n<br>\n";
    $body .= "<hr><br>\n<br>\n";
    $body .= "<b>{$locale['label_duration']}:</b>&nbsp;$duration $month_word<br>\n<br>\n";
    $body .= "<b>{$locale['label_amount']}:</b>&nbsp;$amount_text<br>\n<br>\n";
    $body .= "<b>{$locale['label_vat']}:</b>&nbsp;$vat<br>\n<br>\n";
    $body .= "<b>{$locale['label_total']}:</b>&nbsp;$amount_nexi<br>\n<br>\n";
    $body .= "<b>{$locale['label_datetime']}:</b>&nbsp;$when<br>\n<br>\n";

    if( ! $ok )
    {
        $body .= "<hr><br>\n<br>\n";
        foreach( $locale[ 'failure_lines' ] as $line )
        {
            $body .= "$line<br>\n";
        }
        $body .= "<br>\n";
    }

    $body .= "<hr><br>\n<br>\n";
    $body .= "{$locale['thanks']}<br>\n<br>\n";
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
    $mailer->Timeout    = 15;
    $mailer->getSMTPInstance()->Timelimit = 15;

    $mailer->From       = WORKER_EMAIL_FROM;
    $mailer->FromName   = "Pinaxo Server";
    $mailer->AddAddress( $email, "$firstname $surname" );
    /**/ $mailer->addBCC( "paolo.bertani@me.com", "Paolo Bertani" );

    $mailer->CharSet    = 'utf-8';                                   // Set the email character set
    $mailer->WordWrap   = 80;                                        // Set word wrap to 80 characters
    $mailer->IsHTML( true );                                         // Set email format to HTML

    $body = WorkerEmailThemeApply( $body );

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



function SubscriptionsPaymentEmailLocale( $lang )
{
    $lang = strtolower( trim( (string)$lang ) );

    if( strpos( $lang, '-' ) !== false ) { $lang = explode( '-', $lang )[ 0 ]; }
    if( strpos( $lang, '_' ) !== false ) { $lang = explode( '_', $lang )[ 0 ]; }
    if( strlen( $lang ) > 2 ) { $lang = substr( $lang, 0, 2 ); }

    $locales = [
        'it' => [
            'subject_received' => 'Pinaxo | Pagamento ricevuto',
            'subject_failed' => 'Pinaxo | Pagamento respinto',
            'title_received' => 'Pagamento ricevuto',
            'title_failed' => 'Pagamento respinto',
            'label_user' => 'Utente riferimento',
            'label_email' => 'Email',
            'label_valid_until' => 'Abbonamento valido fino al',
            'label_duration' => 'Durata',
            'label_amount' => 'Imponibile',
            'label_vat' => 'IVA',
            'label_total' => 'Totale',
            'label_datetime' => 'Data e ora',
            'month_singular' => 'mese',
            'month_plural' => 'mesi',
            'amount_formula' => '{amount} euro x {duration} {month_word} = {subtotal} euro',
            'failure_lines' => [
                "Entra nell'area riservata di <b>Pinaxo</b> con username e password.",
                "Seleziona <b>Abbonamento</b> dal menu in alto a sinistra.",
                "Successivamente ritenta il pagamento manualmente.",
                'Per ricevere assistenza scrivi a <b>support@pinaxo.com</b>.'
            ],
            'thanks' => 'Grazie!'
        ],
        'en' => [
            'subject_received' => 'Pinaxo | Payment received',
            'subject_failed' => 'Pinaxo | Payment failed',
            'title_received' => 'Payment received',
            'title_failed' => 'Payment failed',
            'label_user' => 'Subscribed user',
            'label_email' => 'Email',
            'label_valid_until' => 'Subscription valid until',
            'label_duration' => 'Duration',
            'label_amount' => 'Amount',
            'label_vat' => 'VAT/TAX',
            'label_total' => 'Amount due',
            'label_datetime' => 'Date and time',
            'month_singular' => 'month',
            'month_plural' => 'months',
            'amount_formula' => '{amount} euro x {duration} {month_word} = {subtotal} euro',
            'failure_lines' => [
                'Please, enter <b>Pinaxo</b> reserved area with your username and password.',
                'Then select <b>Subscription</b> from the top-left menu.',
                'After that, retry the payment manually.',
                'To receive assistance write us to <b>support@pinaxo.com</b>.'
            ],
            'thanks' => 'Thank you!'
        ],
        'de' => [
            'subject_received' => 'Pinaxo | Zahlung erhalten',
            'subject_failed' => 'Pinaxo | Zahlung fehlgeschlagen',
            'title_received' => 'Zahlung erhalten',
            'title_failed' => 'Zahlung fehlgeschlagen',
            'label_user' => 'Benutzer',
            'label_email' => 'E-Mail',
            'label_valid_until' => 'Abo g&uuml;ltig bis',
            'label_duration' => 'Dauer',
            'label_amount' => 'Betrag',
            'label_vat' => 'MwSt.',
            'label_total' => 'Gesamtbetrag',
            'label_datetime' => 'Datum und Uhrzeit',
            'month_singular' => 'Monat',
            'month_plural' => 'Monate',
            'amount_formula' => '{amount} euro x {duration} {month_word} = {subtotal} euro',
            'failure_lines' => [
                'Bitte melde dich im gesch&uuml;tzten Bereich von <b>Pinaxo</b> mit Benutzername und Passwort an.',
                'W&auml;hle danach im Men&uuml; oben links <b>Abo</b>.',
                'Versuche anschlie&szlig;end die Zahlung erneut.',
                'Bei Bedarf schreibe an <b>support@pinaxo.com</b>.'
            ],
            'thanks' => 'Danke!'
        ],
        'es' => [
            'subject_received' => 'Pinaxo | Pago recibido',
            'subject_failed' => 'Pinaxo | Pago rechazado',
            'title_received' => 'Pago recibido',
            'title_failed' => 'Pago rechazado',
            'label_user' => 'Usuario suscrito',
            'label_email' => 'Correo electr&oacute;nico',
            'label_valid_until' => 'Suscripci&oacute;n v&aacute;lida hasta',
            'label_duration' => 'Duraci&oacute;n',
            'label_amount' => 'Importe',
            'label_vat' => 'IVA/Impuestos',
            'label_total' => 'Importe total',
            'label_datetime' => 'Fecha y hora',
            'month_singular' => 'mes',
            'month_plural' => 'meses',
            'amount_formula' => '{amount} euro x {duration} {month_word} = {subtotal} euro',
            'failure_lines' => [
                'Entra en el &aacute;rea reservada de <b>Pinaxo</b> con tu usuario y contrase&ntilde;a.',
                'Luego selecciona <b>Suscripci&oacute;n</b> en el men&uacute; superior izquierdo.',
                'Despu&eacute;s vuelve a intentar el pago manualmente.',
                'Si necesitas ayuda escribe a <b>support@pinaxo.com</b>.'
            ],
            'thanks' => 'Gracias!'
        ],
        'fr' => [
            'subject_received' => 'Pinaxo | Paiement reçu',
            'subject_failed' => 'Pinaxo | Paiement refusé',
            'title_received' => 'Paiement reçu',
            'title_failed' => 'Paiement refusé',
            'label_user' => 'Utilisateur abonn&eacute;',
            'label_email' => 'E-mail',
            'label_valid_until' => 'Abonnement valable jusqu&#39;au',
            'label_duration' => 'Dur&eacute;e',
            'label_amount' => 'Montant',
            'label_vat' => 'TVA/Taxe',
            'label_total' => 'Montant total',
            'label_datetime' => 'Date et heure',
            'month_singular' => 'mois',
            'month_plural' => 'mois',
            'amount_formula' => '{amount} euro x {duration} {month_word} = {subtotal} euro',
            'failure_lines' => [
                'Veuillez acc&eacute;der &agrave; l&#39;espace r&eacute;serv&eacute; de <b>Pinaxo</b> avec votre identifiant et votre mot de passe.',
                'Ensuite, s&eacute;lectionnez <b>Abonnement</b> dans le menu en haut &agrave; gauche.',
                'Puis r&eacute;essayez le paiement manuellement.',
                'Pour toute assistance, &eacute;crivez &agrave; <b>support@pinaxo.com</b>.'
            ],
            'thanks' => 'Merci!'
        ],
        'ru' => [
            'subject_received' => 'Pinaxo | Оплата получена',
            'subject_failed' => 'Pinaxo | Ошибка оплаты',
            'title_received' => 'Оплата получена',
            'title_failed' => 'Ошибка оплаты',
            'label_user' => 'Пользователь подписки',
            'label_email' => 'Эл. почта',
            'label_valid_until' => 'Подписка действует до',
            'label_duration' => 'Срок',
            'label_amount' => 'Сумма',
            'label_vat' => 'НДС/Налог',
            'label_total' => 'Итого к оплате',
            'label_datetime' => 'Дата и время',
            'month_singular' => 'месяц',
            'month_plural' => 'месяцев',
            'amount_formula' => '{amount} euro x {duration} {month_word} = {subtotal} euro',
            'failure_lines' => [
                'Войдите в закрытую зону <b>Pinaxo</b> с вашим логином и паролем.',
                'Затем выберите <b>Subscription</b> в меню слева сверху.',
                'После этого повторите оплату вручную.',
                'Если нужна помощь, напишите на <b>support@pinaxo.com</b>.'
            ],
            'thanks' => 'Спасибо!'
        ],
        'zh' => [
            'subject_received' => 'Pinaxo | 付款成功',
            'subject_failed' => 'Pinaxo | 付款失败',
            'title_received' => '付款成功',
            'title_failed' => '付款失败',
            'label_user' => '订阅用户',
            'label_email' => '电子邮箱',
            'label_valid_until' => '订阅有效期至',
            'label_duration' => '时长',
            'label_amount' => '金额',
            'label_vat' => '增值税/税费',
            'label_total' => '应付总额',
            'label_datetime' => '日期和时间',
            'month_singular' => '个月',
            'month_plural' => '个月',
            'amount_formula' => '{amount} euro x {duration} {month_word} = {subtotal} euro',
            'failure_lines' => [
                '请使用你的用户名和密码进入 <b>Pinaxo</b> 受限区域。',
                '然后在左上角菜单中选择 <b>Subscription</b>。',
                '之后请手动重新尝试付款。',
                '如需帮助，请联系 <b>support@pinaxo.com</b>。'
            ],
            'thanks' => '谢谢！'
        ]
    ];

    if( ! isset( $locales[ $lang ] ) )
    {
        $lang = 'en';
    }

    return $locales[ $lang ];
}



/*
 *
 *  One month later on the specified day [from controllers/subscriptions.php]
 *
 */

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



/*
 *
 *  Send email to main admins as a `bt` subscription is expired so an incoming bank transfer is expected
 *
 */

function SubscriptionSendMailAsBankTransferIsExpected( $subscription )
{
    $from = $subscription[ 'description' ];
    $amount = round( $subscription[ 'amount' ] * ( 100 + $subscription[ 'vat' ] ) / 100, 2 );

    WorkerLog( WORKER_INFO, "Expected incoming bank transfer from `$subscription", 0 );
}
