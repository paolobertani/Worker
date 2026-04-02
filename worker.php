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

define( 'WORKER_VERSION',           '3.7.15' );  // Worker version number

define( 'PATH_TO_TOOLS',            '/Users/administrator/www/www.pinaxo.com/MacOS/' );             // Path to Pinaxo macOS tools
define( 'PATH_TO_MACSTACK',         '/Applications/MacStack.app/Contents/Resources/usr/local' );    // Path to MacStack runtime root
define( 'PATH_TO_PHP_BIN',          PATH_TO_MACSTACK . '/php/bin/php' );                             // Path to MacStack PHP CLI
define( 'PATH_TO_MYSQLDUMP_BIN',    PATH_TO_MACSTACK . '/mysql/bin/mysqldump' );                    // Path to MacStack mysqldump
define( 'PATH_TO_NGINX_LOG_DIR',    PATH_TO_MACSTACK . '/nginx/log' );                              // Path to MacStack nginx logs
define( 'PATH_TO_CONFIG',           '/Users/administrator/www/www.pinaxo.com/Config/config.json' ); // Path to webapp config file
define( 'PATH_TO_STATUS',           '/Users/administrator/www/www.pinaxo.com/Config/status.json' ); // Path to webapp status file
define( 'PATH_TO_MISC',             '/Users/administrator/www/www.pinaxo.com/misc/' );              // Path to `misc` directory
define( 'PATH_TO_PRIVATE',          '/Users/administrator/www/www.pinaxo.com/html/private/' );      // Path to `private` directory
define( 'PATH_TO_BAD_XLS',          '/Users/administrator/www/www.pinaxo.com/html/badxls/' );       // Path to bad
define( 'PATH_TO_RENEW_CERTS',      dirname( ROOT ) . '/RenewCerts/RenewCerts.php' );               // Path to RenewCerts script

define( 'WORKER_INTERVAL_CACHE',             11 );   // PDF Cache operations time interval
define( 'WORKER_INTERVAL_TOUCH',             90 );   // Touch time interval to keep drives spinning
define( 'WORKER_INTERVAL_DELETEBOTS',     10000 );   // Bot generated records removal time interval
define( 'WORKER_INTERVAL_DATABASES',       1700 );   // Database backup time interval
define( 'WORKER_INTERVAL_LOGROTATE',        509 );   // Log rotation time interval
define( 'WORKER_INTERVAL_IDROLABSTATS',    1508 );   // Idrolab stats generation interval
define( 'WORKER_INTERVAL_EVENTSSMALL',     2100 );   // Cut events_small and recalcs 30days user usage
define( 'WORKER_INTERVAL_PURGESDOCS',      2000 );   // Purge Sent Documents table from spurious records
define( 'WORKER_INTERVAL_DEL_COOKIES',    46800 );   // Delete cookies older than 12 months every 13 hours
define( 'WORKER_INTERVAL_TRIM_USAGE',     50400 );   // Trim usage table every 14 hours
define( 'WORKER_INTERVAL_TRIM_USAGE_PD',  54000 );   // Trim usage_per_document table every 15 hours
define( 'WORKER_INTERVAL_TRIM_USAGE_PU',  57600 );   // Trim usage_per_user table every 16 hours
define( 'WORKER_INTERVAL_TRIM_SEARCHES',  61200 );   // Trim searches_per_brand table every 17 hours
define( 'WORKER_INTERVAL_REBUILD_BPC',     1820 );   // Rebuild brands per category
define( 'WORKER_INTERVAL_XLS',                6 );   // Load XLS price lists
define( 'WORKER_INTERVAL_TRANSCODE',          7 );   // Transcode products codes to match with the codes on the PDFs
define( 'WORKER_INTERVAL_UPDATES_MAILING',  501 );   // Send updates emails to mailing list
define( 'WORKER_INTERVAL_STATS',            502 );   // Build statistics
define( 'WORKER_INTERVAL_QRTABLE',          503 );   // Update QR Code Count Table in Blog Pages (It + En)
define( 'WORKER_INTERVAL_CHECK_PHP_FPM',    504 );   // Check if PHP-FPM need a restart due to Wordpress issues
define( 'WORKER_INTERVAL_SUBSCRIPTIONS',     30 );   // Cash in expired subscriptions
define( 'WORKER_INTERVAL_TRIALS',           302 );   // Suspend users in expired trials
define( 'WORKER_INTERVAL_CHECK_CERT',     86400 );   // 24h - Check cert every day
define( 'WORKER_INTERVAL_RENEW_CERTS',   604800 );   // 7d - Launch RenewCerts weekly
define( 'WORKER_INTERVAL_AUTO_EXPIRE',     1801 );   // Check autoexpire
define( 'WORKER_INTERVAL_AUTO_UNCACHE',    1802 );   // Check remove from cache

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
define( 'WORKER_CASHIN_START_AT',			 10 );	 // Subscription automatic cash-in daily inretval (hour from-to)
define( 'WORKER_CASHIN_END_AT',			     20 );	 // Create a directory named CASHIN/ at ROOT to force immediate cashin
define( 'WORKER_AUTOEXPIRE_YEARS_OLD',        5 );   // After this amount of years from release date a document is considered old
define( 'WORKER_AUTOEXPIRE_YEARS_IGN',        3 );   // If a document in the past X years has never been read and is old must expire
define( 'WORKER_AUTOEXPIRE_YEARS_ADD',        2 );   // How many years add to the release date to set the expire date
define( 'WORKER_AUTOEXPIRE_YEARS_UPL',        2 );   // The old document is set to expired only if has been uploaeded more than Y yrs ago
define( 'WORKER_DONT_CACHE_YEARS_OLD',        3 );   // After this amount of years from expiring a document is considered old
define( 'WORKER_DONT_CACHE_YEARS_IGN',        2 );   // In the past X years this OLD document has never been read: do not cache it

//
// EMAIL CONFIGURATION
//

/*  define( 'WORKER_EMAIL_TO',      '***SECRET***' );
    define( 'WORKER_EMAIL_FROM',    '***SECRET***' );
    define( 'WORKER_EMAIL_NAME',    '***SECRET***' );
    define( 'WORKER_EMAIL_HOST',    '***SECRET***' );
    define( 'WORKER_EMAIL_AUTH',    '***SECRET***' );
    define( 'WORKER_EMAIL_USER',    '***SECRET***' );
    define( 'WORKER_EMAIL_PASS',    '***SECRET***' );
    define( 'WORKER_EMAIL_PORT',    '***SECRET***' );
    define( 'WORKER_EMAIL_SCRE',    '***SECRET***' ); */

//
// DATABASE CONFIGURATION
//

/*  define( 'DB_HOST',              '***SECRET***' );
    define( 'DB_USER',              '***SECRET***' );
    define( 'DB_PASS',              '***SECRET***' );
    define( 'DB_NAME',              '***SECRET***' ); */

//
// REDIS CACHE CONFIGURATION
//

define( 'WORKER_REDIS_HOST',               '127.0.0.1'   );
define( 'WORKER_REDIS_PORT',               6379          );
define( 'WORKER_REDIS_CACHE_DB',           2             );
define( 'WORKER_REDIS_CACHE_NAMESPACE',    'px:dbcache:' );
define( 'WORKER_REDIS_STORE_DB',           3             );
define( 'WORKER_REDIS_STORE_NAMESPACE',    'px:store:'   );
define( 'WORKER_REDIS_TIMEOUT',            0.010         ); // seconds

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

define( 'LOG_DIRECTORIES',  [ PATH_TO_NGINX_LOG_DIR ] );                                       // Path to log directories without trailing slash
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
require_once ROOT . '/include/mtime.php';



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
define( 'BACKUP_DATABASES',         array_key_exists( 'BACKUP_DATABASES', $configuration ) ? (bool) $configuration['BACKUP_DATABASES'] : true );

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
    echo "db backups: " . ( BACKUP_DATABASES ? "enabled\n" : "disabled\n" );
	if( ! HEAVY_DUTY )
	{
    	echo "NEXI: id=" . substr( NEXI_ALIAS_RECURR, -8, 8 ) . " - " . "key=*" . substr( NEXI_KEY_RECURR, -4, 4 );
		echo " - activity hrs: [" . WORKER_CASHIN_START_AT . "-" . WORKER_CASHIN_END_AT . "]" . "\n";
	}
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
