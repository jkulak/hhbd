-- 0010 one-artist-city-table: down. Takes out of artist_city_lookup the pairs the up put in,
-- recreates city_artist_lookup as 0005 left it (InnoDB, the baseline's columns and charset) and
-- refills it from the archive, duplicates and orphans included.
ALTER TABLE `artist_city_lookup` DROP KEY `u_artist_city_lookup`;

DELETE n FROM `artist_city_lookup` n
  JOIN `migration_archive` m
    ON m.`version` = '0010' AND m.`table_name` = 'artist_city_lookup' AND m.`action` = 'inserted'
   AND n.`cityid` = JSON_VALUE(m.`row_data`, '$.cityid') AND n.`artistid` = JSON_VALUE(m.`row_data`, '$.artistid');

CREATE TABLE `city_artist_lookup` (
  `cityid` int(11) NOT NULL DEFAULT 0,
  `artistid` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 999
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

INSERT INTO `city_artist_lookup` (`cityid`, `artistid`, `status`)
SELECT JSON_VALUE(`row_data`, '$.cityid'), JSON_VALUE(`row_data`, '$.artistid'), JSON_VALUE(`row_data`, '$.status')
  FROM `migration_archive`
 WHERE `version` = '0010' AND `table_name` = 'city_artist_lookup' AND `action` = 'deleted'
 ORDER BY `id`;

DELETE FROM `migration_archive` WHERE `version` = '0010';
