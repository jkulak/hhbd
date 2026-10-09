-- 0018 release-date-precision: down. Puts the zero-part dates back, brings back the four
-- placeholders and their credits, and drops the columns and the CHECK. Zero parts need a
-- session that allows them, whatever the server's sql_mode is by then.
SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_IN_DATE', ''), 'NO_ZERO_DATE', '');

ALTER TABLE `albums` DROP CONSTRAINT `ck_albums_year_whole`;

UPDATE `albums` a
  JOIN `migration_archive` m
    ON m.`version` = '0018' AND m.`table_name` = 'albums' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET a.`year` = JSON_VALUE(m.`row_data`, '$.year');

INSERT INTO `albums` (`id`, `legal`, `title`, `release_type`, `urlname`, `labelid`, `year`, `premier`, `media_mc`, `catalog_mc`, `media_cd`, `catalog_cd`, `media_lp`, `catalog_lp`, `media_digital`, `catalog_digital`, `epfor`, `singiel`, `addedby`, `added`, `updatedby`, `updated`, `viewed`, `cover`, `description`, `artistabout`, `notes`, `status`)
SELECT JSON_VALUE(`row_data`, '$.id'), JSON_VALUE(`row_data`, '$.legal'), JSON_VALUE(`row_data`, '$.title'), JSON_VALUE(`row_data`, '$.release_type'), JSON_VALUE(`row_data`, '$.urlname'), JSON_VALUE(`row_data`, '$.labelid'), JSON_VALUE(`row_data`, '$.year'), JSON_VALUE(`row_data`, '$.premier'), JSON_VALUE(`row_data`, '$.media_mc'), JSON_VALUE(`row_data`, '$.catalog_mc'), JSON_VALUE(`row_data`, '$.media_cd'), JSON_VALUE(`row_data`, '$.catalog_cd'), JSON_VALUE(`row_data`, '$.media_lp'), JSON_VALUE(`row_data`, '$.catalog_lp'), JSON_VALUE(`row_data`, '$.media_digital'), JSON_VALUE(`row_data`, '$.catalog_digital'), JSON_VALUE(`row_data`, '$.epfor'), JSON_VALUE(`row_data`, '$.singiel'), JSON_VALUE(`row_data`, '$.addedby'), JSON_VALUE(`row_data`, '$.added'), JSON_VALUE(`row_data`, '$.updatedby'), JSON_VALUE(`row_data`, '$.updated'), JSON_VALUE(`row_data`, '$.viewed'), JSON_VALUE(`row_data`, '$.cover'), JSON_VALUE(`row_data`, '$.description'), JSON_VALUE(`row_data`, '$.artistabout'), JSON_VALUE(`row_data`, '$.notes'), JSON_VALUE(`row_data`, '$.status')
  FROM `migration_archive`
 WHERE `version` = '0018' AND `table_name` = 'albums' AND `action` = 'deleted'
 ORDER BY `id`;

INSERT INTO `album_artist_lookup` (`albumid`, `artistid`, `role`, `position`, `credited_as`, `status`)
SELECT JSON_VALUE(`row_data`, '$.albumid'), JSON_VALUE(`row_data`, '$.artistid'), JSON_VALUE(`row_data`, '$.role'),
       JSON_VALUE(`row_data`, '$.position'), JSON_VALUE(`row_data`, '$.credited_as'), JSON_VALUE(`row_data`, '$.status')
  FROM `migration_archive`
 WHERE `version` = '0018' AND `table_name` = 'album_artist_lookup' AND `action` = 'deleted'
 ORDER BY `id`;

ALTER TABLE `albums` DROP COLUMN `announced`, DROP COLUMN `release_date_precision`;

DELETE FROM `migration_archive` WHERE `version` = '0018';
