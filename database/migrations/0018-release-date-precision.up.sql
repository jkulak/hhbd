-- 0018 release-date-precision: up. A release date says how much of it is known (#54). Albums kept
-- what was not known as zero parts, 2016-11-00 or 2016-00-00 (20 and 134 on production, and two
-- 0000-00-00), which only load because sql_mode tolerates them, and "announced" was read off the
-- date compared with today. The release before this one renders by precision and reads an
-- announced column when there is one.
--
-- A date with zero parts becomes its first day with the precision it had: 2016-11-00 is
-- 2016-11-01, month. 0000-00-00 becomes NULL, not known. A CHECK refuses zero parts from now
-- on. An album is announced when its date is still ahead, or when it looks like an announcement
-- nobody confirmed: a guessed month or year, entered before that date, never edited after it,
-- and still without a single track (8 on production, 2015-05 to 2016-12). The four 2017
-- placeholders entered in 2013 to 2015 go, with their credits (decided on #54): Korzenie, Raj,
-- Katharsis and Jestem Hip Hopem, ids 923, 833, 848, 832, none with a track.
--
-- The placeholders, their credits and every rewritten date are archived (migration_archive,
-- 0009); the down puts them back and drops the columns and the CHECK.
ALTER TABLE `albums`
  ADD COLUMN `release_date_precision` enum('day','month','year') NOT NULL DEFAULT 'day' AFTER `year`,
  ADD COLUMN `announced` tinyint(1) NOT NULL DEFAULT 0 AFTER `release_date_precision`;

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0018', 'albums', 'deleted', JSON_OBJECT('id', a.`id`, 'legal', a.`legal`, 'title', a.`title`, 'release_type', a.`release_type`, 'urlname', a.`urlname`, 'labelid', a.`labelid`, 'year', a.`year`, 'premier', a.`premier`, 'media_mc', a.`media_mc`, 'catalog_mc', a.`catalog_mc`, 'media_cd', a.`media_cd`, 'catalog_cd', a.`catalog_cd`, 'media_lp', a.`media_lp`, 'catalog_lp', a.`catalog_lp`, 'media_digital', a.`media_digital`, 'catalog_digital', a.`catalog_digital`, 'epfor', a.`epfor`, 'singiel', a.`singiel`, 'addedby', a.`addedby`, 'added', a.`added`, 'updatedby', a.`updatedby`, 'updated', a.`updated`, 'viewed', a.`viewed`, 'cover', a.`cover`, 'description', a.`description`, 'artistabout', a.`artistabout`, 'notes', a.`notes`, 'status', a.`status`)
  FROM `albums` a
 WHERE a.`id` IN (923, 833, 848, 832) AND a.`title` IN ('Korzenie', 'Raj', 'Katharsis', 'Jestem Hip Hopem')
   AND NOT EXISTS (SELECT 1 FROM `album_lookup` l WHERE l.`albumid` = a.`id`);

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0018', 'album_artist_lookup', 'deleted', JSON_OBJECT('albumid', c.`albumid`, 'artistid', c.`artistid`, 'role', c.`role`,
       'position', c.`position`, 'credited_as', c.`credited_as`, 'status', c.`status`)
  FROM `album_artist_lookup` c
  JOIN `migration_archive` m
    ON m.`version` = '0018' AND m.`table_name` = 'albums' AND m.`action` = 'deleted'
   AND c.`albumid` = JSON_VALUE(m.`row_data`, '$.id');

DELETE c FROM `album_artist_lookup` c
  JOIN `migration_archive` m
    ON m.`version` = '0018' AND m.`table_name` = 'albums' AND m.`action` = 'deleted'
   AND c.`albumid` = JSON_VALUE(m.`row_data`, '$.id');

DELETE a FROM `albums` a
  JOIN `migration_archive` m
    ON m.`version` = '0018' AND m.`table_name` = 'albums' AND m.`action` = 'deleted'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id');

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0018', 'albums', 'changed', JSON_OBJECT('id', `id`, 'year', `year`)
  FROM `albums`
 WHERE `year` IS NOT NULL AND (MONTH(`year`) = 0 OR DAY(`year`) = 0);

UPDATE `albums` SET `year` = NULL WHERE `year` = '0000-00-00';
UPDATE `albums` SET `release_date_precision` = 'year', `year` = CONCAT(YEAR(`year`), '-01-01')
 WHERE MONTH(`year`) = 0;
UPDATE `albums` SET `release_date_precision` = 'month', `year` = CONCAT(YEAR(`year`), '-', LPAD(MONTH(`year`), 2, '0'), '-01')
 WHERE DAY(`year`) = 0;

UPDATE `albums` a SET a.`announced` = 1
 WHERE a.`year` > CURDATE()
    OR (a.`release_date_precision` <> 'day' AND DATE(a.`added`) < a.`year`
        AND (a.`updated` IS NULL OR DATE(a.`updated`) < a.`year`)
        AND NOT EXISTS (SELECT 1 FROM `album_lookup` l WHERE l.`albumid` = a.`id`));

ALTER TABLE `albums` ADD CONSTRAINT `ck_albums_year_whole` CHECK (`year` IS NULL OR (MONTH(`year`) > 0 AND DAY(`year`) > 0));
