SELECT
        `brand_id`,
        sum(`count`) AS `total`,
        ( SELECT `brand` FROM brands WHERE brands.id = brand_id ) AS `brand`

FROM
        searches_per_brand

WHERE
        `when` > {{start}} AND `when` <= {{end}} AND `category_id` IN ({{::category}})

GROUP BY
        brand_id

HAVING
        `total` > 1000

ORDER BY
        `total` DESC
