<?php

//
//
// Manage Live Action
//
//

function LiveAction()
{

    // Purge table

    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Purge Live Action...", 0, false, false, 1 );

    $error = '';
    $result = QueryExecute( '44_purge_live_action.sql', $error, [ 'time_limit' => time() - 1800 ] );
    if( $result === false )
    {
        EchoNL( "44_purge_live_action.sql: query failed - Error: $error" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'live_action' );

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Purge Live Action: $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);

    /*********/

    return;  // User agents are parsed at runtime via foroco\BrowserDetection

    /*********/


    // Parse User Agents

    $error = '';
    $result = QueryExecute( '45_user_agents_to_parse.sql', $error, [ ] );

    if( $result === false )
    {
        WorkerLog( WORKER_ERROR, "FATAL - 45_user_agents_to_parse.sql: query failed - Error: $error", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( ! is_array( $result ) )
    {
        return;
        /*--- EXIT POINT ---*/
    }

    $n = count( $result );

    if( $n === 0 )
    {
        return;
    }

    $milliseconds = Milliseconds();
    WorkerLog( WORKER_INFO, "Parsing $n User-Agent(s)...", 0, false, false, 1 );

    foreach( $result as $la_row )
    {
        $sha1 = sha1( $la_row[ 'user_agent' ] );

        $info = LiveActionParseUserAgent( $la_row[ 'user_agent' ] );

        LiveActionWriteUserAgentInfo( $la_row[ 'id' ], $info[ 'browser' ], $info[ 'os' ] );
    }

    $milliseconds = Milliseconds( $milliseconds );
    WorkerLog( WORKER_INFO, "Parsing $n User-Agent(s): $milliseconds ms", 0, false, false, 1 );
    WorkerAlive();
    sleep(3);
}



function LiveActionParseUserAgent( $ua )
{
    $error = '';
    $result = QueryExecute( '46_search_user_agent.sql', $error, [ 'sha1' => sha1( $ua ) ] );
    if( $result === false )
    {
        EchoNL( "46_search_user_agent.sql: query failed - Error: $error" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    if( count( $result ) > 0 )
    {
        return $result[ 0 ];
        /*--- EXIT POINT ---*/
    }

    if( WIMB_SERVICE === 'wimb' )
    {
        $post_data = array(
            "user_agent" => $ua,
            "parse_options" => array(
                "allow_servers_to_impersonate_devices" => false,
                "return_metadata_for_useragent" => false,
                "dont_sanitize" => true,
            )
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.whatismybrowser.com/api/v2/user_agent_parse' );
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode( $post_data ) );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'X-API-KEY: ' . WIMB_API_KEY ] );

        $result = curl_exec($ch);
        $curl_info = curl_getinfo($ch);
        //curl_close($ch);

        $result_json = json_decode($result);
        if( $result_json === null ||
            ( ! isset( $result_json->result ) ) ||
            ( ! isset( $result_json->result->code ) ) ||
            ( ! isset( $result_json->parse ) ) )
        {
            return [ 'browser' => '<<WIMB REQUEST FAILED>>', 'os' => '' ];
            /*--- EXIT POINT ---*/
        }

        if( $curl_info['http_code'] != 200 )
        {
            return [ 'browser' => "<<WIMB REQUEST FAILED WITH STATUS {$curl_info['http_code']}>>", 'os' => '' ];
            /*--- EXIT POINT ---*/
        }

        if( $result_json->result->code != "success" )
        {
            return [ 'browser' => "<<WIMB REQUEST FAILED WITH ERROR " . $result_json->result->message_code , 'os' => '' ];
            /*--- EXIT POINT ---*/
        }

        $parse = $result_json->parse;

        // file_put_contents( ROOT . "varexport.txt", "\n-------\n\n$ua\n\n" . json_encode( $result_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

        $info = [
            'browser'   => isset( $parse->software           ) ? $parse->software              : 'UNKNOWN',
            'os'        => isset( $parse->operating_system   ) ? $parse->operating_system      : '',
            'sha1'      => sha1( $ua ),
            'ua'        => $ua
        ];
    }

    if( WIMB_SERVICE === 'userstack' )
    {
        $post_data = [
            'access_key' => USST_API_KEY,
            'ua' => $ua,
            'output' => 'json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://api.userstack.com/detect?' . http_build_query( $post_data ) );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );

        $result = curl_exec($ch);
        $curl_info = curl_getinfo($ch);
        //curl_close($ch);

        if( $curl_info['http_code'] != 200 )
        {
            return [ 'browser' => "<<USST REQUEST FAILED WITH STATUS {$curl_info['http_code']}>>", 'os' => '' ];
            /*--- EXIT POINT ---*/
        }

        $result_json = json_decode($result);
        if( $result_json === null ||
            ( ! property_exists( $result_json, 'os' ) ) ||
            ( ! property_exists( $result_json, 'device' ) ) ||
            ( ! property_exists( $result_json, 'browser' ) ) ||
            ( ! property_exists( $result_json->os, 'name' ) ) ||
            ( ! property_exists( $result_json->device, 'brand' ) ) ||
            ( ! property_exists( $result_json->device, 'name' ) ) ||
            ( ! property_exists( $result_json->device, 'is_mobile_device' ) ) ||
            ( ! property_exists( $result_json->device, 'type' ) ) ||
            ( ! property_exists( $result_json->browser, 'name' ) ) ||
            ( ! property_exists( $result_json->browser, 'version' ) ) )
         {
            return [ 'browser' => '<<USST REQUEST FAILED>>', 'os' => '' ];
            /*--- EXIT POINT ---*/
         }

         $result_json->browser->name    = $result_json->browser->name     === null ? '<unknown>' : $result_json->browser->name;
         $result_json->browser->version = $result_json->browser->version  === null ? '' : $result_json->browser->version;
         $result_json->os->name         = $result_json->os->name          === null ? '<unknown>' : $result_json->os->name;

         $info = [
             'browser'   => "{$result_json->browser->name} {$result_json->browser->version}",
             'os'        => $result_json->os->name,
             'sha1'      => sha1( $ua ),
             'ua'        => $ua
         ];

    }

    if( WIMB_SERVICE === 'bigdatacloud' )
    {
        $post_data = [
            'key' => BDCL_API_KEY,
            'userAgentRaw' => $ua
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.bigdatacloud.net/data/user-agent-info?' . http_build_query( $post_data ) );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );

        $result = curl_exec($ch);
        $curl_info = curl_getinfo($ch);
        //curl_close($ch);

        if( $curl_info['http_code'] != 200 )
        {
            return [ 'browser' => "<<BDCL REQUEST FAILED WITH STATUS {$curl_info['http_code']}>>", 'os' => '' ];
            /*--- EXIT POINT ---*/
        }

        $result_json = json_decode($result);
        if( $result_json === null ||
            ( ! property_exists( $result_json, 'os' ) ) ||
            ( ! property_exists( $result_json, 'userAgent' ) ) )
        {
            return [ 'browser' => '<<BDCL REQUEST FAILED>>', 'os' => '' ];
            /*--- EXIT POINT ---*/
         }

         $info = [
             'browser'   => $result_json->userAgent,
             'os'        => $result_json->os,
             'sha1'      => sha1( $ua ),
             'ua'        => $ua
         ];
    }

    $info[ 'browser'  ] = StringTruncateMaybe( $info[ 'browser'  ], 40 );
    $info[ 'os'       ] = StringTruncateMaybe( $info[ 'os'       ], 40 );
    $info[ 'ua'       ] = StringTruncateMaybe( $info[ 'ua'       ], 255);

    $error = '';
    $result = QueryExecute( '47_save_user_agent.sql', $error, $info );
    if( $result === false )
    {
        EchoNL( "47_save_user_agent.sql: query failed - Error: $error" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'user_agents' );

    return $info;
}



function LiveActionWriteUserAgentInfo( $id, $browser, $os )
{
    $error = '';
    $result = QueryExecute( '48_update_live_action.sql', $error, [ 'id' => $id, 'browser' => $browser, 'os' => $os ] );
    if( $result === false )
    {
        EchoNL( "48_update_live_action.sql': query failed - Error: $error" );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    InvalidateCache( 'live_action' );
}
