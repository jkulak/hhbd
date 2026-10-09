-- 0011 unique-lookups: down. Drops the unique keys (album_artist_lookup gets its plain key on
-- the same columns back), takes out the rows the up put back in place of duplicates, and
-- restores every archived row, so each table holds exactly what it held before.

-- ratings
ALTER TABLE `ratings` DROP KEY `u_ratings`;
INSERT INTO `ratings` (`ID`, `albumid`, `userid`, `rate`, `added`)
SELECT JSON_VALUE(m.`row_data`, '$.ID'), JSON_VALUE(m.`row_data`, '$.albumid'), JSON_VALUE(m.`row_data`, '$.userid'), JSON_VALUE(m.`row_data`, '$.rate'), JSON_VALUE(m.`row_data`, '$.added')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'ratings' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- wishlist
ALTER TABLE `wishlist` DROP KEY `u_wishlist`;
INSERT INTO `wishlist` (`ID`, `albumid`, `userid`, `added`)
SELECT JSON_VALUE(m.`row_data`, '$.ID'), JSON_VALUE(m.`row_data`, '$.albumid'), JSON_VALUE(m.`row_data`, '$.userid'), JSON_VALUE(m.`row_data`, '$.added')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'wishlist' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- collection
ALTER TABLE `collection` DROP KEY `u_collection`;
INSERT INTO `collection` (`ID`, `albumid`, `userid`, `added`)
SELECT JSON_VALUE(m.`row_data`, '$.ID'), JSON_VALUE(m.`row_data`, '$.albumid'), JSON_VALUE(m.`row_data`, '$.userid'), JSON_VALUE(m.`row_data`, '$.added')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'collection' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- city_label_lookup
ALTER TABLE `city_label_lookup` DROP KEY `u_city_label_lookup`;
DELETE t FROM `city_label_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'city_label_lookup' AND m.`action` = 'inserted'
   AND t.`labelid` <=> JSON_VALUE(m.`row_data`, '$.labelid') AND t.`cityid` <=> JSON_VALUE(m.`row_data`, '$.cityid');
INSERT INTO `city_label_lookup` (`labelid`, `cityid`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.labelid'), JSON_VALUE(m.`row_data`, '$.cityid'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'city_label_lookup' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- remix_lookup
ALTER TABLE `remix_lookup` DROP KEY `u_remix_lookup`;
DELETE t FROM `remix_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'remix_lookup' AND m.`action` = 'inserted'
   AND t.`songid` <=> JSON_VALUE(m.`row_data`, '$.songid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid');
INSERT INTO `remix_lookup` (`songid`, `artistid`, `name`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.songid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.name'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'remix_lookup' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- feature_lookup
ALTER TABLE `feature_lookup` DROP KEY `u_feature_lookup`;
DELETE t FROM `feature_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'feature_lookup' AND m.`action` = 'inserted'
   AND t.`songid` <=> JSON_VALUE(m.`row_data`, '$.songid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid') AND t.`feattype` <=> JSON_VALUE(m.`row_data`, '$.feattype');
INSERT INTO `feature_lookup` (`songid`, `artistid`, `feattype`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.songid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.feattype'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'feature_lookup' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- scratch_lookup
ALTER TABLE `scratch_lookup` DROP KEY `u_scratch_lookup`;
DELETE t FROM `scratch_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'scratch_lookup' AND m.`action` = 'inserted'
   AND t.`songid` <=> JSON_VALUE(m.`row_data`, '$.songid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid');
INSERT INTO `scratch_lookup` (`songid`, `artistid`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.songid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'scratch_lookup' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- music_lookup
ALTER TABLE `music_lookup` DROP KEY `u_music_lookup`;
DELETE t FROM `music_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'music_lookup' AND m.`action` = 'inserted'
   AND t.`songid` <=> JSON_VALUE(m.`row_data`, '$.songid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid');
INSERT INTO `music_lookup` (`songid`, `artistid`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.songid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'music_lookup' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- artist_lookup
ALTER TABLE `artist_lookup` DROP KEY `u_artist_lookup`;
DELETE t FROM `artist_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'artist_lookup' AND m.`action` = 'inserted'
   AND t.`songid` <=> JSON_VALUE(m.`row_data`, '$.songid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid');
INSERT INTO `artist_lookup` (`songid`, `artistid`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.songid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'artist_lookup' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- altnames_lookup
ALTER TABLE `altnames_lookup` DROP KEY `u_altnames_lookup`;
DELETE t FROM `altnames_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'altnames_lookup' AND m.`action` = 'inserted'
   AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid') AND t.`altname` <=> CONVERT(JSON_VALUE(m.`row_data`, '$.altname') USING utf8mb3);
INSERT INTO `altnames_lookup` (`artistid`, `altname`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.artistid'), CONVERT(JSON_VALUE(m.`row_data`, '$.altname') USING utf8mb3), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'altnames_lookup' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- band_lookup
ALTER TABLE `band_lookup` DROP KEY `u_band_lookup`;
DELETE t FROM `band_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'band_lookup' AND m.`action` = 'inserted'
   AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid') AND t.`bandid` <=> JSON_VALUE(m.`row_data`, '$.bandid');
INSERT INTO `band_lookup` (`artistid`, `bandid`, `insince`, `awaysince`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.bandid'), JSON_VALUE(m.`row_data`, '$.insince'), JSON_VALUE(m.`row_data`, '$.awaysince'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'band_lookup' AND m.`action` = 'deleted' ORDER BY m.`id`;

-- album_artist_lookup
ALTER TABLE `album_artist_lookup` DROP KEY `u_album_artist_lookup`, ADD KEY `i_album_artist_lookup_albumid_artistid` (`albumid`, `artistid`);
DELETE t FROM `album_artist_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'album_artist_lookup' AND m.`action` = 'inserted'
   AND t.`albumid` <=> JSON_VALUE(m.`row_data`, '$.albumid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid');
INSERT INTO `album_artist_lookup` (`albumid`, `artistid`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.albumid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'album_artist_lookup' AND m.`action` = 'deleted' ORDER BY m.`id`;

DELETE FROM `migration_archive` WHERE `version` = '0011';
