-- 0024 artist-dates: down. Puts every zero and partial date back from the archive, and drops
-- the precisions and the CHECKs. migrate.sh runs it in a session that allows zero dates.
ALTER TABLE `artists` DROP CONSTRAINT `ck_artists_since_whole`, DROP CONSTRAINT `ck_artists_till_whole`;

UPDATE `artists` a
  JOIN `migration_archive` m
    ON m.`version` = '0024' AND m.`table_name` = 'artists' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET a.`since` = JSON_VALUE(m.`row_data`, '$.since'), a.`till` = JSON_VALUE(m.`row_data`, '$.till');

ALTER TABLE `artists` DROP COLUMN `since_precision`, DROP COLUMN `till_precision`;

DELETE FROM `migration_archive` WHERE `version` = '0024';
