<?php

//
//
// CheckPhpFpm
//
//

function CheckPhpFpm()
{
    if( is_dir( '/Users/administrator/www/www.pinaxo.com/DEPLOYMENT-IN-PROGRESS' ) )
    {
        return;
    }

    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Check PHP-FPM is not blocked by Wordpress...", 0, false, false, 1 );


    $restart = false;

    $status = CheckPhpFpmCurl( 'https://www.pinaxo.com/blog/it' );
    if( $status !== 200 )
    {
        $restart = true;
        WorkerLog( WORKER_WARNING, "PHP-FPM: https://www.pinaxo.com/blog/it is not responding, restarting php-fpm; status=$status", 0, true, true, true );
    }

    if( ! $restart )
    {
        $status = CheckPhpFpmCurl( 'https://www.pinaxo.com/blog/en' );
        if( $status !== 200 )
        {
            $restart = true;
            WorkerLog( WORKER_WARNING, "PHP-FPM: https://www.pinaxo.com/blog/en is not responding, restarting php-fpm; status=$status", 0, true, true, true );
        }
    }

    if( $restart )
    {
        if( ! is_dir( "/Users/administrator/www/www.pinaxo.com/restart-php-fpm" ) )
        {
            mkdir( "/Users/administrator/www/www.pinaxo.com/restart-php-fpm" );
        }
    }

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Check PHP-FPM is not blocked by Wordpress: $milliseconds ms", 0, false, false, 1 );
    sleep(3);
}



function CheckPhpFpmCurl( $url, $timeout = 15, &$response = '', &$errnum = 0 )
{
    // Init curl

    $handle = curl_init();


    // Set CURLOPTs

    curl_setopt( $handle, CURLOPT_URL,              $url );
    curl_setopt( $handle, CURLOPT_RETURNTRANSFER,   true );
    curl_setopt( $handle, CURLOPT_FOLLOWLOCATION,   true );
    curl_setopt( $handle, CURLOPT_MAXREDIRS,        3 );

    curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST,   0 );
    curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER,   0 );

    curl_setopt( $handle, CURLOPT_TIMEOUT,          $timeout );


    // Send request, get response

    $response = curl_exec( $handle );


    // Catch error

    $errnum = curl_errno( $handle );
    $error = $errnum == 0 ? '' : curl_strerror( $errnum );


    // Get status

    $status = curl_getinfo( $handle, CURLINFO_HTTP_CODE );


    // Done

    //curl_close( $handle );

    return $status;
}
