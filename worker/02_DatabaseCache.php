<?php

//
//
// Database Cache
//
//

function InvalidateCacheNormalizeTable( $table )
{
    $table = strtolower( trim( str_replace( '`', '', "$table" ) ) );

    return $table;
}



function InvalidateCache( $table )
{
    $table = InvalidateCacheNormalizeTable( $table );

    if( $table === '' )
    {
        WorkerLog( WORKER_WARNING, "Database cache invalidation skipped: empty table name", 0, true, true, true );
        return false;
        /*--- EXIT POINT ---*/
    }

    if( ! class_exists( 'Redis' ) )
    {
        WorkerLog( WORKER_WARNING, "Database cache invalidation failed on `$table`: Redis extension not available", 0, true, true, true );
        return false;
        /*--- EXIT POINT ---*/
    }

    $redis = null;

    try
    {
        $redis = new Redis();

        $connected = $redis->connect( WORKER_REDIS_HOST, WORKER_REDIS_PORT, WORKER_REDIS_TIMEOUT );

        if( ! $connected )
        {
            WorkerLog( WORKER_WARNING, "Database cache invalidation failed on `$table`: cannot connect to Redis", 0, true, true, true );
            return false;
            /*--- EXIT POINT ---*/
        }

        $selected = $redis->select( WORKER_REDIS_CACHE_DB );

        if( ! $selected )
        {
            WorkerLog( WORKER_WARNING, "Database cache invalidation failed on `$table`: cannot select Redis DB " . WORKER_REDIS_CACHE_DB, 0, true, true, true );
            $redis->close();
            return false;
            /*--- EXIT POINT ---*/
        }

        $namespace = WORKER_REDIS_CACHE_NAMESPACE;
        $qPrefix = $namespace . 'q:';
        $tKey = $namespace . 't:' . $table;
        $qKeys = $redis->sMembers( $tKey );

        if( ! is_array( $qKeys ) || count( $qKeys ) === 0 )
        {
            $redis->close();
            return true;
            /*--- EXIT POINT ---*/
        }

        $redis->del( $tKey );

        foreach( $qKeys as $qKey )
        {
            $redis->del( $qKey );

            if( substr( $qKey, 0, strlen( $qPrefix ) ) !== $qPrefix )
            {
                continue;
            }

            $hash = substr( $qKey, strlen( $qPrefix ) );

            if( $hash === '' )
            {
                continue;
            }

            $tpqKey = $namespace . 'tpq:' . $hash;
            $tables = $redis->get( $tpqKey );

            if( $tables === false )
            {
                continue;
            }

            $tables = unserialize( $tables, [ 'allowed_classes' => false ] );

            $redis->del( $tpqKey );

            if( ! is_array( $tables ) )
            {
                continue;
            }

            foreach( $tables as $t )
            {
                $t = InvalidateCacheNormalizeTable( $t );

                if( $t === '' )
                {
                    continue;
                }

                $redis->sRem( $namespace . 't:' . $t, $qKey );
            }
        }

        $redis->close();

        return true;
        /*--- EXIT POINT ---*/
    }
    catch( Throwable $e )
    {
        if( $redis instanceof Redis )
        {
            try
            {
                $redis->close();
            }
            catch( Throwable $e2 )
            {
            }
        }

        WorkerLog( WORKER_WARNING, "Database cache invalidation failed on `$table`: {$e->getMessage()}", 0, true, true, true );

        return false;
        /*--- EXIT POINT ---*/
    }
}
