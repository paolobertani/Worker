SELECT
        `id`,
        `username`,
        `firstname`,
        `surname`,
        `email`,
        `lang`

FROM
        `users`

WHERE
        `id` = {{user_id}}
