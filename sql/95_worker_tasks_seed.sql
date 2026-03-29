INSERT IGNORE INTO
    worker_tasks (
        label,
        last_run
    )
VALUES
    ('hd.qr_table', 'NEVER'),
    ('hd.stats_searches', 'NEVER'),
    ('hd.updates_mailing', 'NEVER'),
    ('light.auto_expire', 'NEVER'),
    ('light.auto_uncache', 'NEVER'),
    ('light.backup_databases', 'NEVER'),
    ('light.check_cert', 'NEVER'),
    ('light.check_php_fpm', 'NEVER'),
    ('light.delete_bot_events', 'NEVER'),
    ('light.events_small', 'NEVER'),
    ('light.expired_cookies_delete', 'NEVER'),
    ('light.expired_sessions_delete', 'NEVER'),
    ('light.idrolab_stats', 'NEVER'),
    ('light.keep_drives_spinning', 'NEVER'),
    ('light.live_action', 'NEVER'),
    ('light.log_rotate', 'NEVER'),
    ('light.manage_pricelist', 'NEVER'),
    ('light.manage_transcode', 'NEVER'),
    ('light.purge_sent_documents', 'NEVER'),
    ('light.rebuild_brands_per_category', 'NEVER'),
    ('light.renew_certs', 'NEVER'),
    ('light.subscriptions', 'NEVER'),
    ('light.trim_searches_per_brand', 'NEVER'),
    ('light.trim_usage', 'NEVER'),
    ('light.trim_usage_per_document', 'NEVER'),
    ('light.trim_usage_per_user', 'NEVER'),
    ('light.trials', 'NEVER'),
    ('light.users_online', 'NEVER');
