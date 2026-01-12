SELECT
         DISTINCT(`norm_search`) AS `norm_search`,
         ANY_VALUE(`search`) AS `search`,
         SUM(`count`) AS `count`

FROM
        searches_per_brand

WHERE
        `brand_id` = {{brand_id}} AND `when` > {{start}} AND `when` <= {{end}} AND `category_id` IN ({{::category}})

GROUP BY
        `norm_search`

ORDER BY
        `count` DESC

LIMIT
        20