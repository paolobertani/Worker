SELECT
        `trials`.`id`,
        `trials`.`user_id`,
        `users`.`group_id`

FROM
        `trials`,
        `users`

WHERE
        `trials`.`valid_until` < CURDATE() AND
        `users`.`group_id` != {{NEXI_EMPTY_GROUP_ID}} AND

        `trials`.`user_id` = `users`.`id`

ORDER BY
        `trials`.`id` DESC

LIMIT 1