-- 0025 band-member-dates: down. Puts every zero and partial date back from the archive, and
-- drops the precisions and the CHECKs. migrate.sh runs it in a session that allows zero dates.
ALTER TABLE `band_lookup` DROP CONSTRAINT `ck_band_lookup_insince_whole`, DROP CONSTRAINT `ck_band_lookup_awaysince_whole`;

UPDATE `band_lookup` b
  JOIN `migration_archive` m
    ON m.`version` = '0025' AND m.`table_name` = 'band_lookup' AND m.`action` = 'changed'
   AND b.`artistid` = JSON_VALUE(m.`row_data`, '$.artistid') AND b.`bandid` = JSON_VALUE(m.`row_data`, '$.bandid')
   SET b.`insince` = JSON_VALUE(m.`row_data`, '$.insince'), b.`awaysince` = JSON_VALUE(m.`row_data`, '$.awaysince');

ALTER TABLE `band_lookup` DROP COLUMN `insince_precision`, DROP COLUMN `awaysince_precision`;

DELETE FROM `migration_archive` WHERE `version` = '0025';
