<?php

//
//
// Rebuild Brands Per Category
//
//

function BrandsPerCategoryRebuild()
{
    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Rebuild brands_per_category...", 0, false, false, 1 );

    $queries = [
        '61_a_rebuild_brands_per_category.sql',
        '61_b_rebuild_brands_per_category.sql',
        '61_c_rebuild_brands_per_category.sql'
    ];

    foreach( $queries as $q)
    {
        $error = '';
        $result = QueryExecute( $q, $error, [] );

        if( $result === false )
        {
            WorkerLog( WORKER_ERROR, "FATAL - $q: query failed - Error: $error", 0, true, true, true );
            WorkerQuitNow();
            /*--- QUIT POINT ---*/
        }
    }

    InvalidateCache( 'brands_per_category' );

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Rebuild brands_per_category: $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}
