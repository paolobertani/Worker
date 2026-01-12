<?php

//
//
// Check PINAXO certificate expiry date
//
//



function CheckCert()
{
    $milliseconds = Milliseconds();

    WorkerLog( WORKER_INFO, "Checking expiry date of Pinaxo cert...", 0, false, false, 1 );

    $domain = "www.pinaxo.com";

    // Create a stream context with SSL options to capture the peer certificate.
    $context = stream_context_create(
    [
        "ssl" =>
        [
            "capture_peer_cert" => true,
            // Disable verification for this example.
            // In production, you should verify the peer certificate!
            "verify_peer"       => false,
            "verify_peer_name"  => false,
        ]
    ] );

    // Open an SSL connection on port 443.
    $client = @stream_socket_client(
        "ssl://{$domain}:443",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if( ! $client )
    {
        WorkerLog( WORKER_WARNING, "CheckCert: Error connecting to {$domain}: {$errstr} ( {$errno} )", 0, true, true, true );
        return;
    }

    // Get stream context parameters which include the certificate.
    $params = stream_context_get_params( $client );

    if( !isset( $params['options']['ssl']['peer_certificate'] ) )
    {
        WorkerLog( WORKER_WARNING, "CheckCert: Could not retrieve the SSL certificate from {$domain}", 0, true, true, true );
        return;
    }

    $certResource = $params['options']['ssl']['peer_certificate'];

    // Parse the certificate using OpenSSL.
    $certInfo = openssl_x509_parse( $certResource );
    if( ! $certInfo )
    {
        WorkerLog( WORKER_WARNING, "CheckCert: Failed to parse the certificate", 0, true, true, true );
        return;
    }

    // Check if the expiration timestamp is available.
    if( ! isset( $certInfo['validTo_time_t'] ) )
    {
        WorkerLog( WORKER_WARNING, "CheckCert: Could not find the expiration date in the certificate", 0, true, true, true );
        return;
    }

    $expirationTimestamp = $certInfo['validTo_time_t'];
    $expirationDate = date( "Y-m-d H:i:s", $expirationTimestamp );

    $days = 14;
    if( $expirationTimestamp - time() < $days * 24 * 3600 )
    {
        WorkerLog( WORKER_WARNING, "CheckCert: $domain expiring in less than $days days [$expirationDate]", 0, true, true, true );
    }

    $milliseconds = Milliseconds( $milliseconds );

    WorkerLog( WORKER_INFO, "Checking expiry date of Pinaxo cert: $milliseconds ms", 0, false, false, 1 );
    sleep( 3 );
}