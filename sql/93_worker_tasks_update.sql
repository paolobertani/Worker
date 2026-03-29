UPDATE
    worker_tasks
SET
    last_run = {{last_run}}
WHERE
    label = {{label}}
LIMIT
    1
