<?php

/*
 *
 *
 *  Set documents to not to be cached when they are expired long ago
 *
 *
 */

function AutoUncache()
{
    WorkerLog( WORKER_INFO, "AutoUncache is disabled: the worker no longer sets new documents to DONT_CACHE = 1", 0, false, false, 1 );
    WorkerAlive();
    return;
    /*--- EXIT POINT ---*/
}


