SELECT
    label,
    last_run_unixtime
FROM
    worker_tasks
ORDER BY
    label ASC
