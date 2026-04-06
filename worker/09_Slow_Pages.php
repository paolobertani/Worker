<?php


/*
 *
 *
 *  Slow rendering pages management
 *
 *
 */



// Globals

$gSlowPages = [];
$gSlowMilliseconds = [];



/*
 *
 *  Clear the slow pages array
 *
 */

function SlowPagesInit()
{
    global $gSlowPages;
    global $gSlowMilliseconds;

    $gSlowPages = [];
    $gSlowMilliseconds = [];
}



/*
 *
 *  Record slow page rendering
 *
 */

function SlowPageRecordMaybe( $milliseconds, $pageNum )
{
    global $gSlowPages;
    global $gSlowMilliseconds;

    if( $milliseconds > WORKER_SLOW_RENDER )
    {
        if( ! in_array( $pageNum, $gSlowPages ) )
        {
            $gSlowPages[] = $pageNum;
            $gSlowMilliseconds[] = $milliseconds;
        }
    }
}



/*
 *
 *  How many pages that take long to render
 *
 */

function SlowPagesCount()
{
    global $gSlowPages;

    return count( $gSlowPages );
}



/*
 *
 *  Return the slow pages and how long they took to render, ordered from the slowest
 *
 */

function SlowPagesGet()
{
    global $gSlowPages;
    global $gSlowMilliseconds;

    ArraySortByArray( $gSlowPages, $gSlowMilliseconds, ARRAY_DESC );

    $slowpages = '[';
    $slowmsecs = '[';

    $n = count( $gSlowPages );

    for( $i = 0; $i < $n; $i++ )
    {
        $p = (string)$gSlowPages[ $i ];
        $m = (string)$gSlowMilliseconds[ $i ];

        if( strlen( $slowpages ) + strlen( $p ) > 250 ) { break; }
        if( strlen( $slowmsecs ) + strlen( $m ) > 250 ) { break; }

        if( $i > 0 )
        {
            $slowpages .= ', ';
            $slowmsecs .= ', ';
        }

        $slowpages .= $p;
        $slowmsecs .= $m;
    }

    $slowpages .= ']';
    $slowmsecs .= ']';

    return [ 'slow_pages' => $slowpages, 'slow_milliseconds' => $slowmsecs ];
}



/*
 *
 *  Return the worst (longest) render time
 *
 */

function SlowPagesWorst()
{
    global $gSlowMilliseconds;

    if( count( $gSlowMilliseconds ) == 0 )
    {
        return 0;
        /*--- EXIT POINT ---*/
    }

    $worst = max( $gSlowMilliseconds );

    return $worst;
}



/*
 *
 *  Update document slow pages fields
 *
 */

function SlowPageUpdateDocument( &$document )
{
    $slowpagescount = SlowPagesCount();
    if( $slowpagescount > 0 )
    {
        $worst = SlowPagesWorst();
        if( $worst < WORKER_VERY_SLOW_RENDER )
        {
            WorkerLog( WORKER_NOTICE, "Slow rendering on $slowpagescount page(s): worst took $worst milliseconds", $document['id'], true, false, false );
            $document['has_slow_pages'] = 1;
        }
        else
        {
            WorkerLog( WORKER_WARNING, "VERY SLOW rendering on $slowpagescount page(s): worst took $worst milliseconds", $document['id'], true, false, false );
            $document['has_slow_pages'] = 2;
        }
    }
    else
    {
        $document['has_slow_pages'] = 0;
    }
    $slow = SlowPagesGet();
    $document['slow_pages'] = $slow['slow_pages'];
    $document['slow_milliseconds'] = $slow['slow_milliseconds'];
}
