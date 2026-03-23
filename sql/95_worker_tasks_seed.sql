INSERT IGNORE INTO
    worker_tasks (
        label,
        last_run_unixtime
    )
VALUES
    ('hd.qr_table', 0),
    ('hd.stats_searches', 0),
    ('hd.updates_mailing', 0),
    ('light.auto_expire', 0),
    ('light.auto_uncache', 0),
    ('light.backup_databases', 0),
    ('light.check_cert', 0),
    ('light.check_php_fpm', 0),
    ('light.delete_bot_events', 0),
    ('light.events_small', 0),
    ('light.expired_cookies_delete', 0),
    ('light.expired_sessions_delete', 0),
    ('light.idrolab_stats', 0),
    ('light.keep_drives_spinning', 0),
    ('light.live_action', 0),
    ('light.log_rotate', 0),
    ('light.manage_pricelist', 0),
    ('light.manage_transcode', 0),
    ('light.purge_sent_documents', 0),
    ('light.rebuild_brands_per_category', 0),
    ('light.subscriptions', 0),
    ('light.trim_searches_per_brand', 0),
    ('light.trim_usage', 0),
    ('light.trim_usage_per_document', 0),
    ('light.trim_usage_per_user', 0),
    ('light.trials', 0),
    ('light.users_online', 0);
