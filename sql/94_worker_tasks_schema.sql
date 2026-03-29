ALTER TABLE
    worker_tasks
CHANGE COLUMN
    task
    label CHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '';

ALTER TABLE
    worker_tasks
CHANGE COLUMN
    last_run_unixtime
    last_run CHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'NEVER';

ALTER TABLE
    worker_tasks
ADD UNIQUE KEY
    worker_tasks_label (label);
