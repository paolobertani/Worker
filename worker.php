<?php

//
//
//
// Pinaxo Worker
//
//
//



        require_once "/Users/administrator/www/www.pinaxo.com/PRIVATE/secrets.php";



//
// ENVIRONMENT INITIALIZATION
//

ini_set( 'serialize_precision', 6 );    // 6 decimal digits for float exported into JSON
ini_set( 'memory_limit', '2048M'  );    // 2GB max memory

//
// CONFIGURATION
//

define( 'ROOT',                     __DIR__ );  // Path to script's directory without trailing slash

define( 'WORKER_VERSION',           '3.7.0' );  // Worker version number

define( 'PATH_TO_TOOLS',            '/Users/administrator/www/www.pinaxo.com/MacOS/' );             // Path to macOS tools
define( 'PATH_TO_CONFIG',           '/Users/administrator/www/www.pinaxo.com/Config/config.json' ); // Path to webapp config file
define( 'PATH_TO_STATUS',           '/Users/administrator/www/www.pinaxo.com/Config/status.json' ); // Path to webapp status file
define( 'PATH_TO_MISC',             '/Users/administrator/www/www.pinaxo.com/misc/' );              // Path to `misc` directory
define( 'PATH_TO_PRIVATE',          '/Users/administrator/www/www.pinaxo.com/html/private/' );      // Path to `private` directory
define( 'PATH_TO_BAD_XLS',          '/Users/administrator/www/www.pinaxo.com/html/badxls/' );       // Path to bad

define( 'WORKER_INTERVAL_CACHE',             11 );   // Cache operations time interval
define( 'WORKER_INTERVAL_TOUCH',             90 );   // Touch time interval to keep drives spinning
define( 'WORKER_INTERVAL_DELETEBOTS',       900 );   // Bot generated records removal time interval
define( 'WORKER_INTERVAL_DATABASES',       1800 );   // Database backup time interval
define( 'WORKER_INTERVAL_LOGROTATE',        600 );   // Log rotation time interval
define( 'WORKER_INTERVAL_IDROLABSTATS',     900 );   // Idrolab stats generation interval
define( 'WORKER_INTERVAL_LIVEACTION',        60 );   // Live Action management interval
define( 'WORKER_INTERVAL_EVENTSSMALL',       40 );   // Cut events_small and recalcs 30days user usage
define( 'WORKER_INTERVAL_PURGESDOCS',       250 );   // Purge Sent Documents table from spurious records
define( 'WORKER_INTERVAL_DEL_EXPS',        1200 );   // Delete expired sessions
define( 'WORKER_INTERVAL_REBUILD_BPC',     3500 );   // Rebuild brands per category
define( 'WORKER_INTERVAL_USERS_ONLINE',      25 );   // Populate users online count table
define( 'WORKER_INTERVAL_XLS',                6 );   // Load XLS price lists
define( 'WORKER_INTERVAL_TRANSCODE',          7 );   // Transcode products codes to match with the codes on the PDFs
define( 'WORKER_INTERVAL_UPDATES_MAILING', 1200 );   // Send updates emails to mailing list
define( 'WORKER_INTERVAL_STATS',           1400 );   // Build statistics
define( 'WORKER_INTERVAL_QRTABLE',         1500 );   // Update QR Code Count Table in Blog Pages (It + En)
define( 'WORKER_INTERVAL_CHECK_PHP_FPM',     45 );   // Check if PHP-FPM need a restart due to Wordpress issues
define( 'WORKER_INTERVAL_SUBSCRIPTIONS',     30 );   // Cash in expired subscriptions
define( 'WORKER_INTERVAL_TRIALS',            29 );   // Suspend users in expired trials
define( 'WORKER_INTERVAL_CHECK_CERT',   3600*24 );   // Check cert every day
define( 'WORKER_INTERVAL_AUTO_EXPIRE',  3600*24 );   // Check autoexpire every day
define( 'WORKER_INTERVAL_AUTO_UNCACHE', 3600*24 );   // Check remove from cache every day

define( 'WORKER_SIGNALS',                  true );   // Use signals (must be supported by PHP)
define( 'WORKER_SLEEP',                       5 );   // Worker sleep time
define( 'WORKER_TOUCH',                      60 );   // Worker touch time
define( 'WORKER_REQUIRED_SPACE',          256.0 );   // Minimum required disk space available on repository volume to operate (GB)
define( 'WORKER_MAX_EMAILS',                 20 );   // Maximum number of email sent per day
define( 'WORKER_SLOW_RENDER',              2500 );   // Rendering a single page is considered "slow" if took more than that (ms)
define( 'WORKER_VERY_SLOW_RENDER',         5000 );   // Rendering a single page is considered "very slow" if took more than that (ms)
define( 'WORKER_MEMORY_LIMIT_MB',          1024 );   // If exceeded then the worker restarts
define( 'WORKER_CACHE_PAGE_LOTS',           100 );   // How many pages to render at each iteration; set to 0 to render every page
define( 'WORKER_CACHE_PAUSE',       '1:30-8:00' );   // Pause cache generation around backups
define( 'WORKER_CACHE_MAKES_COLORS',      false );   // Are pagescolor built during cache generation
define( 'WORKER_AUTOEXPIRE_YEARS_OLD',        5 );   // After this amount of years from release date a document is considered old
define( 'WORKER_AUTOEXPIRE_YEARS_IGN',        3 );   // If a document in the past X years hass never been read and is old must expire
define( 'WORKER_AUTOEXPIRE_YEARS_ADD',        2 );   // How many years add to the release date to set the expire date
define( 'WORKER_DONT_CACHE_YEARS_OLD',        3 );   // After this amount of years from expiring a document is considered old
define( 'WORKER_DONT_CACHE_YEARS_IGN',        2 );   // In the past X years this OLD document has never been read: do not cache it

//
// EMAIL CONFIGURATION
//

/*  define( 'WORKER_EMAIL_TO',      '***' );
    define( 'WORKER_EMAIL_FROM',    '***' );
    define( 'WORKER_EMAIL_NAME',    '***' );
    define( 'WORKER_EMAIL_HOST',    '***' );
    define( 'WORKER_EMAIL_AUTH',    '***' );
    define( 'WORKER_EMAIL_USER',    '***' );
    define( 'WORKER_EMAIL_PASS',    '***' );
    define( 'WORKER_EMAIL_PORT',    '***' );
    define( 'WORKER_EMAIL_SCRE',    '***' ); */

//
// DATABASE CONFIGURATION
//

/*  define( 'DB_HOST',                  '***' );
    define( 'DB_USER',                  '***' );
    define( 'DB_PASS',                  '***' );
    define( 'DB_NAME',                  '***' ); */

//
// DATABASE BACKUP CONFIGURATION
//

define( 'BDB_PATH',         '/Users/administrator/Backup/mysql' );                              // Path to databases backup directory without trailing slash
define( 'BDB_PATH_SLAVE',   '/Volumes/Backup23HD/Users/administrator/Backup/mysql' );           // Path to databases backup directory without trailing slash in slave volume
define( 'BDB_EXCLUDES',     'mysql,information_schema,performance_schema,sys,kalei::events,kalei::geoip,kalei::system_log' ); // Tables and databases to exclude from backup
define( 'BDB_PAUSE',        '1:50-2:59' );                                                      // Pause db backups during system backup

//
// Credit Card Keys
//

/*define( 'NEXI_PRODUCTION', *** );

if( NEXI_PRODUCTION )
{
	*************
	*			*
	*	 2024   *
	*			*
	*************


 //	define( 'NEXI_URL_NORMAL',      '***' );
 //	define( 'NEXI_URL_RECURR',      '***' );
 //	define( 'NEXI_ALIAS_NORMAL',    '***' );
 //	define( 'NEXI_ALIAS_RECURR',    '***' );
 //	define( 'NEXI_KEY_NORMAL',      '***' );
 //	define( 'NEXI_KEY_RECURR',      '***' );
 //	define( 'NEXI_EMPTY_GROUP_ID',  '***' );


	*************
	*			*
	*	 2026   *
	*			*
	*************


 define( 'NEXI_URL_NORMAL',      '***' );
 define( 'NEXI_URL_RECURR',      '***' );
 define( 'NEXI_ALIAS_NORMAL',    '***' );
 define( 'NEXI_ALIAS_RECURR',    '***' );
 define( 'NEXI_KEY_NORMAL',      '***' );
 define( 'NEXI_KEY_RECURR',      '***' );
 define( 'NEXI_EMPTY_GROUP_ID',  '***' );
}
else // testing
{
 define( 'NEXI_URL_NORMAL',      '***' );
 define( 'NEXI_URL_RECURR',      '***' );
 define( 'NEXI_ALIAS_NORMAL',    '***' );
 define( 'NEXI_ALIAS_RECURR',    '***' );
 define( 'NEXI_KEY_NORMAL',      '***' );
 define( 'NEXI_KEY_RECURR',      '***' );
 define( 'NEXI_EMPTY_GROUP_ID',  '***' );
}*/



//
// IDROLAB
//

define( 'IDR_PAUSE',        '0:00-6:59' );                                                      // When to pause idrolab tagging (during scheduled backups)

//
// LOG ROTATION CONFIGURATION
//

define( 'LOG_DIRECTORIES',  [ '/usr/local/nginx/log' ] );                                       // Path to log directories without trailing slash
define( 'LOG_SIZE',         1024 * 1024 * 2 );                                                  // Max size of log file

//
// What's my browser what's my browser.com
//                   ^^^^^^^^^^^^^^^^^^^^^

/*  define( 'WIMB_API_KEY', '***' );
    define( 'USST_API_KEY', '***' );
    define( 'BDCL_API_KEY', '***' );
    define( 'WIMB_SERVICE', '***' ); */

//
// STATS
//

define( 'STATS_FIRST_YEAR',             2022 );
define( 'STATS_EXCLUDED_CATEGORIES',   '8,9,11,12' );

//
// CURL COOKIES
//

define( 'CURL_COOKIES',   ROOT . "/tmp/worker_cookies.txt" );

//
// RESOLUTIONS, PACKING AND QUALITY: see Packing&Resolution
//



//
// INCLUDE
//

require_once ROOT . '/include/3rd-parts/phpmailer/PHPMailer.php';
require_once ROOT . '/include/3rd-parts/phpmailer/SMTP.php';
require_once ROOT . '/include/3rd-parts/phpmailer/Exception.php';
require_once ROOT . '/include/3rd-parts/phpspreadsheet/autoload.php';

require_once ROOT . '/include/strings.php';
require_once ROOT . '/include/milliseconds.php';
require_once ROOT . '/include/echo.php';
require_once ROOT . '/include/query.php';
require_once ROOT . '/include/arrays.php';
require_once ROOT . '/include/arguments.php';
require_once ROOT . '/include/fs.php';
require_once ROOT . '/include/mailer.php';
require_once ROOT . '/include/exec.php';
require_once ROOT . '/include/curl.php';



//
// INCLUDE WORKER
//

$worker = scandir( ROOT . '/worker' );
foreach( $worker as $w )
{
    if( substr( $w, 0, 1 ) !== '.' && substr( $w, -4, 4 ) === '.php' )
    {
        require_once ROOT . '/worker/' . $w;
    }
}



//
// PARSE CONFIG FILE
//

if( ! FileExists( PATH_TO_CONFIG ) ) { echo "config file missing\n"; exit(0); }
$configuration = json_decode( file_get_contents( PATH_TO_CONFIG ), true );
if( $configuration === null ) { echo "failed parsing config file\n"; exit(0); }



//
// CONFIG DEPENDENT CONSTANTS
//

define( 'WORKER_MACHINE',           $configuration['MACHINE'] );                    // Machine name the worker is running on

define( 'MASTER_STORAGE_DIR',       $configuration['MASTER_STORAGE_DIR' ] );        // Path to storage directory
define( 'SLAVE_STORAGE_DIR',        $configuration['SLAVE_STORAGE_DIR' ] );         // Path to storage directory in slave volume (empty string or equal to master if not present)


define( 'PATH_TO_INBOX',            '/Users/administrator/www/www.pinaxo.com/tmp/inbox/');  // Path to sent document inbox


//
// HEAVY DUTY & Process Name
//

define( 'HEAVY_DUTY', ArgumentGet( 'hd', ARGUMENT_BOOLEAN ) );
define( 'WORKER_PROCESS', HEAVY_DUTY ? "worker_heavy_duty" : "worker" );
define( 'RESTARTED', ArgumentGet( 'restart', ARGUMENT_BOOLEAN ) );



//
// MAIN
//



// Check arguments for "immediate" mode

$gWorkerImmediate = true;
$terminate = WorkerRunWithArgs();
if( $terminate )
{
    WorkerQuitNow();
    /*--- EXIT POINT ---*/
}


// Run in batch mode

$gWorkerImmediate = false;

if( ! RESTARTED )
{
    echo "Worker - version " . WORKER_VERSION . "\n";
    echo "heavy duty: " . ( HEAVY_DUTY ? "yes\n" : "no\n" );
    echo "started: " . date( 'd/m/Y H:i:s' ) . "\n";
    echo "machine: " . WORKER_MACHINE . "\n";
    echo "pid: " . getmypid() . "\n";
}
else
{
    echo "Worker RESTARTED - v." . WORKER_VERSION . " - " . ( HEAVY_DUTY ? "hd - " : "" ) . date( 'd/m/Y H:i:s' ) . "\n";
}


if( WORKER_SIGNALS )
{
    SignalInstall();
    if( ! RESTARTED ) echo "to stop press `Ctrl-C`\n---\n";
}
else
{
    if( ! RESTARTED ) echo "to stop create a file named `stop` in the worker's directory\n---\n";
}

if( ! RESTARTED ) WorkerLog( WORKER_INFO, 'Worker' . ( HEAVY_DUTY ? ' Heavy duty ' : ' ' ) . 'started | Version '   . WORKER_VERSION, null, true, true, false );
if(   RESTARTED ) WorkerLog( WORKER_INFO, 'Worker' . ( HEAVY_DUTY ? ' Heavy duty ' : ' ' ) . 'restarted | Version ' . WORKER_VERSION, null, true, false, true );

WorkerRun();

//
//
//



