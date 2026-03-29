<?php

//
//
// Produces statistics on searches per brand per category per year (Trends)
//
//

function StatsSearchesBuild()
{
    // should build?

    $sslbd = "searches_stats_last_build_date";
    $ssbst = "searches_stats_build_status";
    $today = date( "Y-m-d" );
    $last = StatusGetValue( $sslbd );
    if( $last === $today )
    {
        return;
        /*--- EXIT POINT ---*/
    }
    StatusSetValue( $sslbd, $today );
    StatusSetValue( $ssbst, 'building' );


    // starting

    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Building searches stats per brand...", 0, false, false, 1 );


    // get the categories

    $categories = StatsSearchesGetCategories();
    $all_categories = implode( ',', $categories );


    // timespan - stats are produced for the current year starting one year before

    $this_year = intval( date( 'Y' ) );
    $last_year = $this_year - 1;
    $this_month = date( 'm' );

    $start = "$last_year-$this_month"; // start month is excluded
    $end   = "$this_year-$this_month"; //   end month is included


    // iterate over categories

    foreach( $categories as $category_id )
    {
        if( $category_id === 0 )
        {
            $category_param = $all_categories;
        }
        else
        {
            $category_param = "$category_id";
        }


        // get the brands

        $error = '';
        $query = '90_stats_searches_get_brands.sql';
        $result = QueryExecute( $query, $error, [ 'start' => $start, 'end' => $end, '::category' => $category_param ] );
        if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }

        $brands = $result;
        $n = count( $brands );
        $i = 0;

        foreach( $brands as $brand )
        {
            $i++;

            $brand_id = intval( $brand['brand_id'] );

            WorkerLog( WORKER_INFO, "Building searches stats per category $category_id, brand [$i:$n] {$brand['brand']}...", 0, false, false, 1 );

            $error = '';
            $query = '91_stats_searches_get_searches.sql';
            $result = QueryExecute( $query, $error, [ 'start' => $start, 'end' => $end, 'brand_id' => $brand_id, '::category' => $category_param ] );
            if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }

            $searches = $result;

            $pricelist = StatsSearchesLoadPricelist( $brand_id );

            foreach( $searches as &$search )
            {
                $search['count'] = intval( $search['count'] );
                $search['weight'] = $search['count'] / $brand['total'] * 100;
                $search['search'] = StatsSearchesNormalizeSearch( $search['search'] );
                $search['description'] = StatsSearchesGetDescription( $pricelist, $search['search'] );
            } unset( $search );

            StatsSearchesSave( $this_year, $category_id, $brand_id, $searches );

            WorkerAlive();
        }

        // Store which brands have stats at least in one category or all aggregated categories

        if( $category_id === 0 )
        {
            $error = '';
            $query = 'UPDATE `brands` SET `has_trends` = 0';
            $result = QueryExecute( $query, $error );
            if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }
            InvalidateCache( 'brands' );


            $brand_ids = [];
            foreach( $brands as $brand )
            {
                $brand_ids[] = $brand['brand_id'];
            }
            $brand_ids = implode( ',', $brand_ids );

            $error = '';
            $query = "UPDATE `brands` SET `has_trends` = 1 WHERE `id` IN ($brand_ids)";
            $result = QueryExecute( $query, $error );
            if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }
            InvalidateCache( 'brands' );
        }
    }


    // Register stats are ready

    StatusSetValue( $ssbst, 'ready' );


    // Log

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Built searches stats per brand: $milliseconds ms", 0, true, false, 1 );
    sleep(3);
}



function StatsSearchesNormalizeSearch( $s )
{
    $s = mb_strtolower( $s );
    $s = trim( $s, "=\\!* " );
    return $s;
}



function StatsSearchesLoadPricelist( $brand_id )
{
    $brand_dir = PathToBrandDirectory( $brand_id );

    if( ! FSDirectoryExists( $brand_dir ) )
    {
        WorkerLog( WORKER_ERROR, "FATAL - Missing directory of brand $brand ($brand_id)", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( ! is_file( $brand_dir . 'pricelist.txt' ) )
    {
        return [];
    }

    $pricelist = ArrayFromFile( $brand_dir . 'pricelist.txt' );

    foreach( $pricelist as &$product )
    {
        unset( $product['srch'] );
        unset( $product['lngh'] );
        unset( $product['prce'] );

        $product['code'] = mb_strtolower( $product['code'] );
        $product['dscr'] = mb_strtolower( $product['dscr'] );

    } unset( $product );

    return $pricelist;
}



function StatsSearchesGetDescription( $pricelist, $code )
{
    if( count( $pricelist ) === 0 || strlen( $code ) < 4 )
    {
        return "";
    }

    // find all the products whose code begins with `$code`

    $matches = [];

    $len = strlen( $code );

    foreach( $pricelist as $product )
    {
        if( substr( $product['code'], 0, $len ) === $code )
        {
            $matches[] = $product['dscr'];
        }
    }

    if( count( $matches ) === 0 )
    {
        return "";
    }

    // extract the part of the description all the products start with

    $descr = $matches[0];

    foreach( $matches as $m )
    {
        $d = '';
        $l = min( strlen( $m ), strlen( $descr ) );
        for( $i = 0; $i < $l; $i++ )
        {
            if( $m[$i] === $descr[$i] )
            {
                $d .= $m[$i];
            }
            else
            {
                break;
            }
        }
        if( strlen( $d ) === 0 ) break;
        $descr = $d;
    }
    $descr = trim( $descr );

    return $descr;
}



function StatsSearchesSave( $year, $category_id, $brand_id, $searches )
{
    $dir = PathToBrandDirectory( $brand_id ) . "stats/searches/$year/$year.$category_id";
    $filename = "stats.searches.$brand_id.$year.$category_id.txt";
    FSMakeDirectoryTree( $dir );
    ArrayToFile( "$dir/$filename", $searches );
}



function StatsSearchesGetCategories()
{
    $excluded_cats = STATS_EXCLUDED_CATEGORIES;
    $error = '';
    $query = "SELECT `id` FROM `categories` WHERE `id` NOT IN ($excluded_cats)";
    $result = QueryExecute( $query, $error );
    if( $result === false ) { WorkerLog( WORKER_ERROR, "FATAL - $query: query failed - Error: $error", 0, true, true, true ); WorkerQuitNow(); /* QUIT */ }
    $categories = [];
    foreach( $result as $record )
    {
        $categories[] = intval( $record['id'] );
    }
    $categories[] = 0; // zero 0 stands for all categories (or category unspecified =0 in the 2022 records)

    return $categories;
}
