-- 0016 album-discs: up. A track's disc gets a column of its own (#59). Until now an album of
-- several discs stored disc * 100 + position in track (1 348 rows on production, up to 406),
-- and the importer, which gets disc and position separately, had nowhere to put them. The
-- release before this one reads both shapes, so the tracklists look the same afterwards.
--
-- Rows naming an album that does not exist go (22 on production, 17 of them album 583's). A
-- track stored at position 0 on an album that exists becomes the last one on its disc, where a
-- hidden or bonus track sits (6 on production). Every row of every album this touches is
-- archived first (migration_archive, 0009), as it was; the down replaces those albums' rows with the archived
-- ones. Restoring row by row would not be exact: on an album holding a tracklist twice, a
-- track decoded from 105 and one stored as 5 become the same row.
--
-- No unique key on (albumid, disc, track) yet: on production 180 positions on 22 albums still
-- hold two or more songs, mostly two editions' tracklists on one album, which needs a person;
-- they are listed on #59.
ALTER TABLE `album_lookup` ADD COLUMN `disc` tinyint(4) NOT NULL DEFAULT 1 AFTER `albumid`;

-- Every row of an album that does not exist, or that has a track to decode or to place.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0016', 'album_lookup', 'deleted', JSON_OBJECT('songid', l.`songid`, 'albumid', l.`albumid`, 'track', l.`track`, 'status', l.`status`)
  FROM `album_lookup` l
 WHERE NOT EXISTS (SELECT 1 FROM `albums` a WHERE a.`id` = l.`albumid`)
    OR l.`albumid` IN (SELECT `albumid` FROM `album_lookup` WHERE `track` >= 100 OR `track` = 0);

DELETE l FROM `album_lookup` l
 WHERE NOT EXISTS (SELECT 1 FROM `albums` a WHERE a.`id` = l.`albumid`);

UPDATE `album_lookup` SET `disc` = FLOOR(`track` / 100), `track` = `track` MOD 100 WHERE `track` >= 100;

UPDATE `album_lookup` z
  JOIN (SELECT t.`songid`, t.`albumid`, m.`top` + ROW_NUMBER() OVER (PARTITION BY t.`albumid` ORDER BY t.`songid`) AS `pos`
          FROM `album_lookup` t
          JOIN (SELECT `albumid`, MAX(`track`) AS `top` FROM `album_lookup` WHERE `disc` = 1 GROUP BY `albumid`) m ON m.`albumid` = t.`albumid`
         WHERE t.`track` = 0) p
    ON p.`songid` = z.`songid` AND p.`albumid` = z.`albumid` AND z.`track` = 0
   SET z.`track` = p.`pos`;
