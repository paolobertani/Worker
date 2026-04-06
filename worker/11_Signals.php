<?php

/*
 *
 *
 *  Signal
 *
 *
 */



/*
 *
 *  Global
 *
 */

$gShutdown = false;



/*
 *
 *  Listen for quit (from ActivityMonitor)
 *  kill -s 15 <pid> from terminal
 *  or CTRL-C from terminal
 *
 */

function SignalInstall()
{
    declare(ticks = 1);

    global $gShutdown;
    $gShutdown = false;

    $success = true;
    $success = $success & pcntl_signal( SIGTERM, 'SignalHandlerPrivate' ); // Quit or kill -s 15 <pid>
    $success = $success & pcntl_signal( SIGINT,  'SignalHandlerPrivate' ); // Ctrl-C

    if( ! $success )
    {
        EchoNL( 'FATAL - SignalInstall: could not install signal handler' );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



/*
 *
 *  A "quit" signal has been received
 *
 */

function SignalQuitReceived()
{
    global $gShutdown;
    return $gShutdown;
}



/*
 *
 *  PRIVATE
 *
 */

function SignalHandlerPrivate( $signo, $siginfo )
{
    global $gShutdown;
    $gShutdown = true;
}

