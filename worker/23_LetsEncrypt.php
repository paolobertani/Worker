<?php

/*
 *
 *
 *  LetsEncrypt
 *
 *
 */



function RenewCertsLaunch()
{
    $milliseconds = Milliseconds();

    WorkerLog( WORKER_INFO, "Launching RenewCerts in background...", 0, false, false, 1 );

    $command = escapeshellarg( PATH_TO_PHP_BIN ) . ' ' . escapeshellarg( PATH_TO_RENEW_CERTS ) . ' >/dev/null 2>&1 & echo $!';

    $output = [];
    $status = 0;

    exec( $command, $output, $status );

    $pid = count( $output ) === 0 ? 0 : intval( trim( $output[ 0 ] ) );

    if( $status !== 0 || $pid <= 0 )
    {
        WorkerLog( WORKER_ERROR, "Failed launching RenewCerts in background", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $milliseconds = Milliseconds( $milliseconds );

    WorkerLog( WORKER_INFO, "Launching RenewCerts in background: pid=$pid - $milliseconds ms", 0, false, false, 1 );
}
