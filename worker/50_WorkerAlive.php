<?php

/*
 *
 *
 *  WorkerAlive
 *
 *
 */

$gWorkerLive = 0;

function WorkerAlive()
{
    global $gWorkerLive;
    if( time() - $gWorkerLive > 5 )
    {
        $gWorkerLive = time();
        file_put_contents( PATH_TO_MISC . WORKER_PROCESS . ".txt", $gWorkerLive );
    }
}
