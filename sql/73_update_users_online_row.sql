UPDATE `users_online`

SET

`users_count`   = GREATEST( {{users}},   `users_count` ),
`public_count`  = GREATEST( {{public}},  `public_count`),
`total_count`   = GREATEST( {{total}},   `total_count` )

WHERE

`when` = {{when}}