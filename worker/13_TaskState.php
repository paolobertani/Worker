<?php

//
//
// TaskState
//
//

$gWorkerTaskState = [];



//
// Task specs
//

function WorkerTaskSpecs()
{
    static $specs = null;

    if( $specs !== null )
    {
        return $specs;
        /*--- EXIT POINT ---*/
    }

    $specs = [
        'hd.updates_mailing' => [
            'label'      => 'hd.updates_mailing',
            'interval'   => WORKER_INTERVAL_UPDATES_MAILING,
            'enabled_if' => 'WorkerTaskIsHeavyDuty',
            'run'        => 'WorkerTaskRunUpdatesMailing',
        ],
        'hd.stats_searches' => [
            'label'      => 'hd.stats_searches',
            'interval'   => WORKER_INTERVAL_STATS,
            'enabled_if' => 'WorkerTaskIsHeavyDuty',
            'allowed_if' => 'WorkerTaskCanRunStatsSearches',
            'run'        => 'WorkerTaskRunStatsSearches',
        ],
        'hd.qr_table' => [
            'label'      => 'hd.qr_table',
            'interval'   => WORKER_INTERVAL_QRTABLE,
            'enabled_if' => 'WorkerTaskIsHeavyDuty',
            'run'        => 'WorkerTaskRunQrTable',
        ],

        'light.keep_drives_spinning' => [
            'label'      => 'light.keep_drives_spinning',
            'interval'   => WORKER_INTERVAL_TOUCH,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunKeepDrivesSpinning',
        ],
        'light.subscriptions' => [
            'label'      => 'light.subscriptions',
            'interval'   => WORKER_INTERVAL_SUBSCRIPTIONS,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'allowed_if' => 'WorkerTaskCanRunSubscriptions',
            'force_if'   => 'WorkerTaskShouldForceSubscriptions',
            'run'        => 'WorkerTaskRunSubscriptions',
        ],
        'light.trials' => [
            'label'      => 'light.trials',
            'interval'   => WORKER_INTERVAL_TRIALS,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunTrials',
        ],
        'light.delete_bot_events' => [
            'label'      => 'light.delete_bot_events',
            'interval'   => WORKER_INTERVAL_DELETEBOTS,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunDeleteBotEvents',
        ],
        'light.backup_databases' => [
            'label'      => 'light.backup_databases',
            'interval'   => WORKER_INTERVAL_DATABASES,
            'enabled_if' => 'WorkerTaskCanBackupDatabases',
            'run'        => 'WorkerTaskRunBackupDatabases',
        ],
        'light.log_rotate' => [
            'label'      => 'light.log_rotate',
            'interval'   => WORKER_INTERVAL_LOGROTATE,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunLogRotate',
        ],
        'light.idrolab_stats' => [
            'label'      => 'light.idrolab_stats',
            'interval'   => WORKER_INTERVAL_IDROLABSTATS,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunIdrolabStats',
        ],
        'light.live_action' => [
            'label'      => 'light.live_action',
            'interval'   => WORKER_INTERVAL_LIVEACTION,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunLiveAction',
        ],
        'light.events_small' => [
            'label'      => 'light.events_small',
            'interval'   => WORKER_INTERVAL_EVENTSSMALL,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunEventsSmall',
        ],
        'light.purge_sent_documents' => [
            'label'      => 'light.purge_sent_documents',
            'interval'   => WORKER_INTERVAL_PURGESDOCS,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunPurgeSentDocuments',
        ],
        'light.expired_sessions_delete' => [
            'label'      => 'light.expired_sessions_delete',
            'interval'   => WORKER_INTERVAL_DEL_EXPS,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunExpiredSessionsDelete',
        ],
        'light.expired_cookies_delete' => [
            'label'      => 'light.expired_cookies_delete',
            'interval'   => WORKER_INTERVAL_DEL_COOKIES,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunExpiredCookiesDelete',
        ],
        'light.trim_usage' => [
            'label'      => 'light.trim_usage',
            'interval'   => WORKER_INTERVAL_TRIM_USAGE,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunTrimUsage',
        ],
        'light.trim_usage_per_document' => [
            'label'      => 'light.trim_usage_per_document',
            'interval'   => WORKER_INTERVAL_TRIM_USAGE_PD,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunTrimUsagePerDocument',
        ],
        'light.trim_usage_per_user' => [
            'label'      => 'light.trim_usage_per_user',
            'interval'   => WORKER_INTERVAL_TRIM_USAGE_PU,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunTrimUsagePerUser',
        ],
        'light.trim_searches_per_brand' => [
            'label'      => 'light.trim_searches_per_brand',
            'interval'   => WORKER_INTERVAL_TRIM_SEARCHES,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunTrimSearchesPerBrand',
        ],
        'light.rebuild_brands_per_category' => [
            'label'      => 'light.rebuild_brands_per_category',
            'interval'   => WORKER_INTERVAL_REBUILD_BPC,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunRebuildBrandsPerCategory',
        ],
        'light.users_online' => [
            'label'      => 'light.users_online',
            'interval'   => WORKER_INTERVAL_USERS_ONLINE,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunUsersOnline',
        ],
        'light.manage_pricelist' => [
            'label'      => 'light.manage_pricelist',
            'interval'   => WORKER_INTERVAL_XLS,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunManagePricelist',
        ],
        'light.manage_transcode' => [
            'label'      => 'light.manage_transcode',
            'interval'   => WORKER_INTERVAL_TRANSCODE,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunManageTranscode',
        ],
        'light.check_php_fpm' => [
            'label'      => 'light.check_php_fpm',
            'interval'   => WORKER_INTERVAL_CHECK_PHP_FPM,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunCheckPhpFpm',
        ],
        'light.check_cert' => [
            'label'      => 'light.check_cert',
            'interval'   => WORKER_INTERVAL_CHECK_CERT,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunCheckCert',
        ],
        'light.auto_expire' => [
            'label'      => 'light.auto_expire',
            'interval'   => WORKER_INTERVAL_AUTO_EXPIRE,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunAutoExpire',
        ],
        'light.auto_uncache' => [
            'label'      => 'light.auto_uncache',
            'interval'   => WORKER_INTERVAL_AUTO_UNCACHE,
            'enabled_if' => 'WorkerTaskIsLightDuty',
            'run'        => 'WorkerTaskRunAutoUncache',
        ],
    ];

    return $specs;
}



//
// State load / access
//

function WorkerTasksStateLoad()
{
    global $gWorkerTaskState;

    $rows = DbWorkerTasksAll();
    $gWorkerTaskState = [];

    foreach( $rows as $row )
    {
        $gWorkerTaskState[ $row['label'] ] = intval( $row['last_run_unixtime'] );
    }

    $missing = [];

    foreach( WorkerTaskSpecs() as $label => $spec )
    {
        if( ! array_key_exists( $label, $gWorkerTaskState ) )
        {
            $missing[] = $label;
        }
    }

    if( count( $missing ) > 0 )
    {
        WorkerLog( WORKER_ERROR, 'Missing worker_tasks rows for labels: ' . implode( ', ', $missing ), 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }
}



function WorkerTaskLastRun( $label )
{
    global $gWorkerTaskState;

    if( ! array_key_exists( $label, $gWorkerTaskState ) )
    {
        WorkerLog( WORKER_ERROR, "Worker task label not loaded: $label", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    return intval( $gWorkerTaskState[ $label ] );
}



function WorkerTaskSetLastRun( $label, $when = null )
{
    global $gWorkerTaskState;

    if( $when === null )
    {
        $when = time();
    }

    DbWorkerTaskUpdate( $label, $when );
    $gWorkerTaskState[ $label ] = intval( $when );
}



//
// Scheduler
//

function WorkerTaskRun( $label )
{
    $specs = WorkerTaskSpecs();

    if( ! array_key_exists( $label, $specs ) )
    {
        WorkerLog( WORKER_ERROR, "Worker task not configured: $label", 0, true, true, true );
        WorkerQuitNow();
        /*--- QUIT POINT ---*/
    }

    $spec = $specs[ $label ];

    if( array_key_exists( 'enabled_if', $spec ) && ! call_user_func( $spec['enabled_if'] ) )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    $forced = array_key_exists( 'force_if', $spec ) && call_user_func( $spec['force_if'] );

    if( ! $forced && time() - WorkerTaskLastRun( $label ) <= $spec['interval'] )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    if( ! $forced && array_key_exists( 'allowed_if', $spec ) && ! call_user_func( $spec['allowed_if'] ) )
    {
        return false;
        /*--- EXIT POINT ---*/
    }

    call_user_func( $spec['run'] );
    WorkerTaskSetLastRun( $label );
    WorkerAlive();

    return true;
}



//
// Conditions
//

function WorkerTaskIsHeavyDuty()
{
    return HEAVY_DUTY;
}



function WorkerTaskIsLightDuty()
{
    return ! HEAVY_DUTY;
}



function WorkerTaskCanBackupDatabases()
{
    return ! HEAVY_DUTY && BACKUP_DATABASES;
}



function WorkerTaskCanRunStatsSearches()
{
    return intval( date( 'G' ) ) >= 20;
}



function WorkerTaskCanRunSubscriptions()
{
    $hour = intval( date( 'G' ) );
    return $hour >= WORKER_CASHIN_START_AT && $hour <= WORKER_CASHIN_END_AT;
}



function WorkerTaskShouldForceSubscriptions()
{
    return is_dir( ROOT . '/CASHIN' );
}



//
// Task wrappers
//

function WorkerTaskRunUpdatesMailing()
{
    UpdatesNotify();
    UpdatesSend();
}



function WorkerTaskRunStatsSearches()
{
    StatsSearchesBuild();
}



function WorkerTaskRunQrTable()
{
    UpdateQrCountTablePage();
}



function WorkerTaskRunKeepDrivesSpinning()
{
    KeepDrivesSpinning();
}



function WorkerTaskRunSubscriptions()
{
    if( is_dir( ROOT . '/CASHIN' ) )
    {
        exec( 'mv ' . ROOT . '/CASHIN' . ' ' . ROOT . '/CASHIN-EXECUTED' );
    }

    Subscriptions();
}



function WorkerTaskRunTrials()
{
    Trials();
}



function WorkerTaskRunDeleteBotEvents()
{
    DeleteBotEvents();
}



function WorkerTaskRunBackupDatabases()
{
    BackupDatabases();
}



function WorkerTaskRunLogRotate()
{
    LogRotate();
}



function WorkerTaskRunIdrolabStats()
{
    IdrolabDoStats();
}



function WorkerTaskRunLiveAction()
{
    LiveAction();
}



function WorkerTaskRunEventsSmall()
{
    EventsSmall();
}



function WorkerTaskRunPurgeSentDocuments()
{
    PurgeSentDocuments();
}



function WorkerTaskRunExpiredSessionsDelete()
{
    ExpiredSessionsDelete();
}



function WorkerTaskRunExpiredCookiesDelete()
{
    ExpiredCookiesDelete();
}



function WorkerTaskRunTrimUsage()
{
    TrimUsage();
}



function WorkerTaskRunTrimUsagePerDocument()
{
    TrimUsagePerDocument();
}



function WorkerTaskRunTrimUsagePerUser()
{
    TrimUsagePerUser();
}



function WorkerTaskRunTrimSearchesPerBrand()
{
    TrimSearchesPerBrand();
}



function WorkerTaskRunRebuildBrandsPerCategory()
{
    BrandsPerCategoryRebuild();
}



function WorkerTaskRunUsersOnline()
{
    UsersOnline();
}



function WorkerTaskRunManagePricelist()
{
    $brand_op = ManagePricelist();
    if( $brand_op !== false )
    {
        SlaveSyncBrands( [ $brand_op ] );
    }
}



function WorkerTaskRunManageTranscode()
{
    $brand_op = ManageTranscode();
    if( $brand_op !== false )
    {
        SlaveSyncBrands( [ $brand_op ] );
    }
}



function WorkerTaskRunCheckPhpFpm()
{
    CheckPhpFpm();
}



function WorkerTaskRunCheckCert()
{
    CheckCert();
}



function WorkerTaskRunAutoExpire()
{
    AutoExpire();
}



function WorkerTaskRunAutoUncache()
{
    AutoUncache();
}
