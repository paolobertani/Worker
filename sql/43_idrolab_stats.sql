SELECT
    ( SELECT NOW() ) AS `now`,
	( SELECT count(`id`) FROM `events` where `action` in ( "view","publicview" ) and `when` >= ( DATE_FORMAT( (NOW() - INTERVAL 90 DAY), "%Y-%m-%d 00:00:00" ) ) ) AS "last_3_months_views",
	( SELECT count(`id`) FROM `documents` where `type` = "C" AND `expire` >= DATE_FORMAT(NOW(),"%Y-%m") AND `pdf`=1 and `status`="" and category_id not in (8,9) ) as "catalogues",
	( SELECT count(`id`) FROM `documents` where `type` = "L" AND `expire` >= DATE_FORMAT(NOW(),"%Y-%m") AND `pdf`=1 and `status`="" and category_id not in (8,9) ) as "price_lists",
	( SELECT count(`id`)+1 FROM `brands` where default_category not in (8,9) and occasional=0 ) as "partners"
