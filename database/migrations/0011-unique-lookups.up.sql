-- 0011 unique-lookups: up. No link table had a unique key, and duplicates had crept in (#57); a
-- bulk import with per-track credits would multiply them unless the database refuses. Each
-- table gets the key that says what one row means, after its duplicates are removed.
--
-- Production on 2026-10-09 held these surplus rows, all exact copies of a row that stays:
-- album_artist_lookup 7, band_lookup 8, artist_lookup 1, music_lookup 3, collection 3,
-- ratings 2; none in altnames_lookup, scratch_lookup, feature_lookup, remix_lookup,
-- city_label_lookup or wishlist.
--
-- A table without an id keeps one row per key: every row of a duplicated key is archived, and
-- one goes back with the highest status (for band_lookup also the earliest insince and the
-- latest awaysince). A table with an ID keeps the oldest row and archives the later ones. The
-- down drops the keys and puts every archived row back (migration_archive, 0009).
--
-- Left out, with the reasons recorded on #57:
--   album_lookup: 162 positions on 19 albums hold two or more different songs, so no
--     (albumid, track) key holds until the data is reviewed and discs are told apart.
--   remix_lookup: keyed on (songid, artistid) alone; all 109 rows have no name, and a NULL in
--     a unique key binds nothing.
--   artist_city_lookup has its key since 0010, and city_artist_lookup is gone.
--
-- Matching a key uses <=> so a NULL key is found like any other.

-- album_artist_lookup: one row per albumid, artistid.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'album_artist_lookup', 'deleted', JSON_OBJECT('albumid', t.`albumid`, 'artistid', t.`artistid`, 'status', t.`status`)
  FROM `album_artist_lookup` t
  JOIN (SELECT `albumid`, `artistid` FROM `album_artist_lookup` GROUP BY `albumid`, `artistid` HAVING COUNT(*) > 1) d ON d.`albumid` <=> t.`albumid` AND d.`artistid` <=> t.`artistid`;
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'album_artist_lookup', 'inserted', JSON_OBJECT('albumid', `albumid`, 'artistid', `artistid`, 'status', MAX(`status`))
  FROM `album_artist_lookup` GROUP BY `albumid`, `artistid` HAVING COUNT(*) > 1;
DELETE t FROM `album_artist_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'album_artist_lookup' AND m.`action` = 'inserted'
   AND t.`albumid` <=> JSON_VALUE(m.`row_data`, '$.albumid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid');
INSERT INTO `album_artist_lookup` (`albumid`, `artistid`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.albumid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'album_artist_lookup' AND m.`action` = 'inserted';
-- The unique key replaces the plain one on the same columns.
ALTER TABLE `album_artist_lookup` DROP KEY `i_album_artist_lookup_albumid_artistid`, ADD UNIQUE KEY `u_album_artist_lookup` (`albumid`, `artistid`);

-- band_lookup: one row per artistid, bandid.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'band_lookup', 'deleted', JSON_OBJECT('artistid', t.`artistid`, 'bandid', t.`bandid`, 'insince', t.`insince`, 'awaysince', t.`awaysince`, 'status', t.`status`)
  FROM `band_lookup` t
  JOIN (SELECT `artistid`, `bandid` FROM `band_lookup` GROUP BY `artistid`, `bandid` HAVING COUNT(*) > 1) d ON d.`artistid` <=> t.`artistid` AND d.`bandid` <=> t.`bandid`;
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'band_lookup', 'inserted', JSON_OBJECT('artistid', `artistid`, 'bandid', `bandid`, 'insince', MIN(`insince`), 'awaysince', MAX(`awaysince`), 'status', MAX(`status`))
  FROM `band_lookup` GROUP BY `artistid`, `bandid` HAVING COUNT(*) > 1;
DELETE t FROM `band_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'band_lookup' AND m.`action` = 'inserted'
   AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid') AND t.`bandid` <=> JSON_VALUE(m.`row_data`, '$.bandid');
INSERT INTO `band_lookup` (`artistid`, `bandid`, `insince`, `awaysince`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.bandid'), JSON_VALUE(m.`row_data`, '$.insince'), JSON_VALUE(m.`row_data`, '$.awaysince'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'band_lookup' AND m.`action` = 'inserted';
ALTER TABLE `band_lookup` ADD UNIQUE KEY `u_band_lookup` (`artistid`, `bandid`);

-- altnames_lookup: one row per artistid, altname.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'altnames_lookup', 'deleted', JSON_OBJECT('artistid', t.`artistid`, 'altname', t.`altname`, 'status', t.`status`)
  FROM `altnames_lookup` t
  JOIN (SELECT `artistid`, `altname` FROM `altnames_lookup` GROUP BY `artistid`, `altname` HAVING COUNT(*) > 1) d ON d.`artistid` <=> t.`artistid` AND d.`altname` <=> t.`altname`;
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'altnames_lookup', 'inserted', JSON_OBJECT('artistid', `artistid`, 'altname', `altname`, 'status', MAX(`status`))
  FROM `altnames_lookup` GROUP BY `artistid`, `altname` HAVING COUNT(*) > 1;
DELETE t FROM `altnames_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'altnames_lookup' AND m.`action` = 'inserted'
   AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid') AND t.`altname` <=> CONVERT(JSON_VALUE(m.`row_data`, '$.altname') USING utf8mb3);
INSERT INTO `altnames_lookup` (`artistid`, `altname`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.artistid'), CONVERT(JSON_VALUE(m.`row_data`, '$.altname') USING utf8mb3), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'altnames_lookup' AND m.`action` = 'inserted';
ALTER TABLE `altnames_lookup` ADD UNIQUE KEY `u_altnames_lookup` (`artistid`, `altname`(190));

-- artist_lookup: one row per songid, artistid.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'artist_lookup', 'deleted', JSON_OBJECT('songid', t.`songid`, 'artistid', t.`artistid`, 'status', t.`status`)
  FROM `artist_lookup` t
  JOIN (SELECT `songid`, `artistid` FROM `artist_lookup` GROUP BY `songid`, `artistid` HAVING COUNT(*) > 1) d ON d.`songid` <=> t.`songid` AND d.`artistid` <=> t.`artistid`;
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'artist_lookup', 'inserted', JSON_OBJECT('songid', `songid`, 'artistid', `artistid`, 'status', MAX(`status`))
  FROM `artist_lookup` GROUP BY `songid`, `artistid` HAVING COUNT(*) > 1;
DELETE t FROM `artist_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'artist_lookup' AND m.`action` = 'inserted'
   AND t.`songid` <=> JSON_VALUE(m.`row_data`, '$.songid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid');
INSERT INTO `artist_lookup` (`songid`, `artistid`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.songid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'artist_lookup' AND m.`action` = 'inserted';
ALTER TABLE `artist_lookup` ADD UNIQUE KEY `u_artist_lookup` (`songid`, `artistid`);

-- music_lookup: one row per songid, artistid.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'music_lookup', 'deleted', JSON_OBJECT('songid', t.`songid`, 'artistid', t.`artistid`, 'status', t.`status`)
  FROM `music_lookup` t
  JOIN (SELECT `songid`, `artistid` FROM `music_lookup` GROUP BY `songid`, `artistid` HAVING COUNT(*) > 1) d ON d.`songid` <=> t.`songid` AND d.`artistid` <=> t.`artistid`;
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'music_lookup', 'inserted', JSON_OBJECT('songid', `songid`, 'artistid', `artistid`, 'status', MAX(`status`))
  FROM `music_lookup` GROUP BY `songid`, `artistid` HAVING COUNT(*) > 1;
DELETE t FROM `music_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'music_lookup' AND m.`action` = 'inserted'
   AND t.`songid` <=> JSON_VALUE(m.`row_data`, '$.songid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid');
INSERT INTO `music_lookup` (`songid`, `artistid`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.songid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'music_lookup' AND m.`action` = 'inserted';
ALTER TABLE `music_lookup` ADD UNIQUE KEY `u_music_lookup` (`songid`, `artistid`);

-- scratch_lookup: one row per songid, artistid.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'scratch_lookup', 'deleted', JSON_OBJECT('songid', t.`songid`, 'artistid', t.`artistid`, 'status', t.`status`)
  FROM `scratch_lookup` t
  JOIN (SELECT `songid`, `artistid` FROM `scratch_lookup` GROUP BY `songid`, `artistid` HAVING COUNT(*) > 1) d ON d.`songid` <=> t.`songid` AND d.`artistid` <=> t.`artistid`;
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'scratch_lookup', 'inserted', JSON_OBJECT('songid', `songid`, 'artistid', `artistid`, 'status', MAX(`status`))
  FROM `scratch_lookup` GROUP BY `songid`, `artistid` HAVING COUNT(*) > 1;
DELETE t FROM `scratch_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'scratch_lookup' AND m.`action` = 'inserted'
   AND t.`songid` <=> JSON_VALUE(m.`row_data`, '$.songid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid');
INSERT INTO `scratch_lookup` (`songid`, `artistid`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.songid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'scratch_lookup' AND m.`action` = 'inserted';
ALTER TABLE `scratch_lookup` ADD UNIQUE KEY `u_scratch_lookup` (`songid`, `artistid`);

-- feature_lookup: one row per songid, artistid, feattype.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'feature_lookup', 'deleted', JSON_OBJECT('songid', t.`songid`, 'artistid', t.`artistid`, 'feattype', t.`feattype`, 'status', t.`status`)
  FROM `feature_lookup` t
  JOIN (SELECT `songid`, `artistid`, `feattype` FROM `feature_lookup` GROUP BY `songid`, `artistid`, `feattype` HAVING COUNT(*) > 1) d ON d.`songid` <=> t.`songid` AND d.`artistid` <=> t.`artistid` AND d.`feattype` <=> t.`feattype`;
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'feature_lookup', 'inserted', JSON_OBJECT('songid', `songid`, 'artistid', `artistid`, 'feattype', `feattype`, 'status', MAX(`status`))
  FROM `feature_lookup` GROUP BY `songid`, `artistid`, `feattype` HAVING COUNT(*) > 1;
DELETE t FROM `feature_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'feature_lookup' AND m.`action` = 'inserted'
   AND t.`songid` <=> JSON_VALUE(m.`row_data`, '$.songid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid') AND t.`feattype` <=> JSON_VALUE(m.`row_data`, '$.feattype');
INSERT INTO `feature_lookup` (`songid`, `artistid`, `feattype`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.songid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.feattype'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'feature_lookup' AND m.`action` = 'inserted';
ALTER TABLE `feature_lookup` ADD UNIQUE KEY `u_feature_lookup` (`songid`, `artistid`, `feattype`);

-- remix_lookup: one row per songid, artistid.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'remix_lookup', 'deleted', JSON_OBJECT('songid', t.`songid`, 'artistid', t.`artistid`, 'name', t.`name`, 'status', t.`status`)
  FROM `remix_lookup` t
  JOIN (SELECT `songid`, `artistid` FROM `remix_lookup` GROUP BY `songid`, `artistid` HAVING COUNT(*) > 1) d ON d.`songid` <=> t.`songid` AND d.`artistid` <=> t.`artistid`;
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'remix_lookup', 'inserted', JSON_OBJECT('songid', `songid`, 'artistid', `artistid`, 'name', MAX(`name`), 'status', MAX(`status`))
  FROM `remix_lookup` GROUP BY `songid`, `artistid` HAVING COUNT(*) > 1;
DELETE t FROM `remix_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'remix_lookup' AND m.`action` = 'inserted'
   AND t.`songid` <=> JSON_VALUE(m.`row_data`, '$.songid') AND t.`artistid` <=> JSON_VALUE(m.`row_data`, '$.artistid');
INSERT INTO `remix_lookup` (`songid`, `artistid`, `name`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.songid'), JSON_VALUE(m.`row_data`, '$.artistid'), JSON_VALUE(m.`row_data`, '$.name'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'remix_lookup' AND m.`action` = 'inserted';
ALTER TABLE `remix_lookup` ADD UNIQUE KEY `u_remix_lookup` (`songid`, `artistid`);

-- city_label_lookup: one row per labelid, cityid.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'city_label_lookup', 'deleted', JSON_OBJECT('labelid', t.`labelid`, 'cityid', t.`cityid`, 'status', t.`status`)
  FROM `city_label_lookup` t
  JOIN (SELECT `labelid`, `cityid` FROM `city_label_lookup` GROUP BY `labelid`, `cityid` HAVING COUNT(*) > 1) d ON d.`labelid` <=> t.`labelid` AND d.`cityid` <=> t.`cityid`;
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'city_label_lookup', 'inserted', JSON_OBJECT('labelid', `labelid`, 'cityid', `cityid`, 'status', MAX(`status`))
  FROM `city_label_lookup` GROUP BY `labelid`, `cityid` HAVING COUNT(*) > 1;
DELETE t FROM `city_label_lookup` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'city_label_lookup' AND m.`action` = 'inserted'
   AND t.`labelid` <=> JSON_VALUE(m.`row_data`, '$.labelid') AND t.`cityid` <=> JSON_VALUE(m.`row_data`, '$.cityid');
INSERT INTO `city_label_lookup` (`labelid`, `cityid`, `status`)
SELECT JSON_VALUE(m.`row_data`, '$.labelid'), JSON_VALUE(m.`row_data`, '$.cityid'), JSON_VALUE(m.`row_data`, '$.status')
  FROM `migration_archive` m WHERE m.`version` = '0011' AND m.`table_name` = 'city_label_lookup' AND m.`action` = 'inserted';
ALTER TABLE `city_label_lookup` ADD UNIQUE KEY `u_city_label_lookup` (`labelid`, `cityid`);

-- collection: one row per albumid, userid, the oldest.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'collection', 'deleted', JSON_OBJECT('ID', t.`ID`, 'albumid', t.`albumid`, 'userid', t.`userid`, 'added', t.`added`)
  FROM `collection` t
 WHERE EXISTS (SELECT 1 FROM `collection` k WHERE k.`albumid` = t.`albumid` AND k.`userid` = t.`userid` AND k.`ID` < t.`ID`);
DELETE t FROM `collection` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'collection' AND m.`action` = 'deleted'
   AND t.`ID` = JSON_VALUE(m.`row_data`, '$.ID');
ALTER TABLE `collection` ADD UNIQUE KEY `u_collection` (`albumid`, `userid`);

-- wishlist: one row per albumid, userid, the oldest.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'wishlist', 'deleted', JSON_OBJECT('ID', t.`ID`, 'albumid', t.`albumid`, 'userid', t.`userid`, 'added', t.`added`)
  FROM `wishlist` t
 WHERE EXISTS (SELECT 1 FROM `wishlist` k WHERE k.`albumid` = t.`albumid` AND k.`userid` = t.`userid` AND k.`ID` < t.`ID`);
DELETE t FROM `wishlist` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'wishlist' AND m.`action` = 'deleted'
   AND t.`ID` = JSON_VALUE(m.`row_data`, '$.ID');
ALTER TABLE `wishlist` ADD UNIQUE KEY `u_wishlist` (`albumid`, `userid`);

-- ratings: one row per albumid, userid, the oldest.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0011', 'ratings', 'deleted', JSON_OBJECT('ID', t.`ID`, 'albumid', t.`albumid`, 'userid', t.`userid`, 'rate', t.`rate`, 'added', t.`added`)
  FROM `ratings` t
 WHERE EXISTS (SELECT 1 FROM `ratings` k WHERE k.`albumid` = t.`albumid` AND k.`userid` = t.`userid` AND k.`ID` < t.`ID`);
DELETE t FROM `ratings` t JOIN `migration_archive` m ON m.`version` = '0011' AND m.`table_name` = 'ratings' AND m.`action` = 'deleted'
   AND t.`ID` = JSON_VALUE(m.`row_data`, '$.ID');
ALTER TABLE `ratings` ADD UNIQUE KEY `u_ratings` (`albumid`, `userid`);
