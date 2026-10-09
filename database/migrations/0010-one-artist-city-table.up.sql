-- 0010 one-artist-city-table: up. The application reads artist_city_lookup; the archived
-- backoffice wrote city_artist_lookup, so most artist-city links never showed (#64): on
-- production 171 pairs were only in the old table, against 56 rows in the one the pages read;
-- 168 of them name an artist and a city that exist, which leaves 224 rows.
-- The old table's pairs move into the one the application reads, the old table goes, and a
-- unique key stops a pair from being stored twice.
--
-- A pair the old table held more than once goes in once, with the highest status among its
-- copies (the pages ignore status). Rows naming an artist or a city that does not exist do not
-- go in. Every row of the old table is archived first, and so is every row inserted, so the
-- down puts both tables back as they were.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0010', 'city_artist_lookup', 'deleted', JSON_OBJECT('cityid', `cityid`, 'artistid', `artistid`, 'status', `status`)
  FROM `city_artist_lookup`;

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0010', 'artist_city_lookup', 'inserted', JSON_OBJECT('cityid', o.`cityid`, 'artistid', o.`artistid`, 'status', MAX(o.`status`))
  FROM `city_artist_lookup` o
  JOIN `artists` a ON a.`id` = o.`artistid`
  JOIN `cities` c ON c.`id` = o.`cityid`
  LEFT JOIN `artist_city_lookup` n ON n.`cityid` = o.`cityid` AND n.`artistid` = o.`artistid`
 WHERE n.`artistid` IS NULL
 GROUP BY o.`cityid`, o.`artistid`;

INSERT INTO `artist_city_lookup` (`cityid`, `artistid`, `status`)
SELECT JSON_VALUE(`row_data`, '$.cityid'), JSON_VALUE(`row_data`, '$.artistid'), JSON_VALUE(`row_data`, '$.status')
  FROM `migration_archive`
 WHERE `version` = '0010' AND `table_name` = 'artist_city_lookup' AND `action` = 'inserted';

DROP TABLE `city_artist_lookup`;

-- Artist first: the pages look cities up by artist.
ALTER TABLE `artist_city_lookup` ADD UNIQUE KEY `u_artist_city_lookup` (`artistid`, `cityid`);
