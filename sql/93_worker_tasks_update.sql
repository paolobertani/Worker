UPDATE
    worker_tasks
SET
    last_run_unixtime = {{last_run_unixtime}}
WHERE
    label = {{label}}
LIMIT
    1
