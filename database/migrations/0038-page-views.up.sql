-- 0038 page-views: up. The views of each page a day, for popularity over a week, a month or a
-- year (#68, #194). albums.viewed and the others keep the total since the site began; this
-- keeps when, by day, nothing about who.
CREATE TABLE `page_views` (
  `entity_type` enum('album','artist','song','label','news') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `day` date NOT NULL,
  `views` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`entity_type`, `day`, `entity_id`),
  KEY `i_page_views_entity` (`entity_type`, `entity_id`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
