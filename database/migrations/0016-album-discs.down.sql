-- 0016 album-discs: down. Replaces the rows of every album the up touched with the rows archived
-- before it, which brings back the old track numbers and the rows of the album that does not
-- exist, and drops the disc column.
DELETE l FROM `album_lookup` l
  JOIN (SELECT DISTINCT JSON_VALUE(`row_data`, '$.albumid') AS `albumid`
          FROM `migration_archive` WHERE `version` = '0016' AND `table_name` = 'album_lookup') a
    ON l.`albumid` = a.`albumid`;

ALTER TABLE `album_lookup` DROP COLUMN `disc`;

INSERT INTO `album_lookup` (`songid`, `albumid`, `track`, `status`)
SELECT JSON_VALUE(`row_data`, '$.songid'), JSON_VALUE(`row_data`, '$.albumid'), JSON_VALUE(`row_data`, '$.track'), JSON_VALUE(`row_data`, '$.status')
  FROM `migration_archive`
 WHERE `version` = '0016' AND `table_name` = 'album_lookup' AND `action` = 'deleted'
 ORDER BY `id`;

DELETE FROM `migration_archive` WHERE `version` = '0016';
