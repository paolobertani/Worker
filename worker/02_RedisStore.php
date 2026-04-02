<?php

//
//
// Redis Store
//
//

//
// Connect to the Redis DB that backs Pinaxo stores
//

function WorkerStoreRedisConnect()
{
    if( ! class_exists( 'Redis' ) )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $redis = new Redis();

    if( ! $redis->connect( WORKER_REDIS_HOST, WORKER_REDIS_PORT, WORKER_REDIS_TIMEOUT ) )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    if( ! $redis->select( WORKER_REDIS_STORE_DB ) )
    {
        $redis->close();
        return false;
        /*--- EXIT POINT ---*/
    }

    return $redis;
}



//
// Return the count of users online based on the Redis live_action index
//

function WorkerStoreLiveActionGetOnlineUsersCount()
{
    $redis = WorkerStoreRedisConnect();

    if( $redis === false )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $onlineUsersKey = WORKER_REDIS_STORE_NAMESPACE . 'live_action_online';

    $redis->zRemRangeByScore( $onlineUsersKey, '-inf', time() - 1800 );

    $count = $redis->zCount( $onlineUsersKey, time() - 300, '+inf' );

    $redis->close();

    return intval( $count );
}
