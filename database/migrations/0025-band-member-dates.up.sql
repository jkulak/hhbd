-- 0025 band-member-dates: up. When a member joined a band and left it are whole dates or NULL
-- (#88). On production on 2026-10-09, 577 joins and 581 departures were 0000-00-00, not known,
-- which become NULL; 8 and 4 were partial and become the first day of their year or month with
-- a precision, as 0024 does for artists. Every changed value is archived by its artist and
-- band, the pair 0011 made unique, and a CHECK refuses zero parts from now on.
ALTER TABLE `band_lookup`
  ADD COLUMN `insince_precision` enum('day','month','year') NOT NULL DEFAULT 'day' AFTER `insince`,
  ADD COLUMN `awaysince_precision` enum('day','month','year') NOT NULL DEFAULT 'day' AFTER `awaysince`;

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0025', 'band_lookup', 'changed', JSON_OBJECT('artistid', `artistid`, 'bandid', `bandid`,
       'insince', CAST(`insince` AS CHAR), 'awaysince', CAST(`awaysince` AS CHAR))
  FROM `band_lookup`
 WHERE MONTH(`insince`) = 0 OR DAY(`insince`) = 0 OR MONTH(`awaysince`) = 0 OR DAY(`awaysince`) = 0;

UPDATE `band_lookup` SET `insince` = NULL WHERE `insince` = '0000-00-00';
UPDATE `band_lookup` SET `insince_precision` = 'year', `insince` = CONCAT(YEAR(`insince`), '-01-01') WHERE MONTH(`insince`) = 0;
UPDATE `band_lookup` SET `insince_precision` = 'month', `insince` = CONCAT(YEAR(`insince`), '-', LPAD(MONTH(`insince`), 2, '0'), '-01') WHERE DAY(`insince`) = 0;
UPDATE `band_lookup` SET `awaysince` = NULL WHERE `awaysince` = '0000-00-00';
UPDATE `band_lookup` SET `awaysince_precision` = 'year', `awaysince` = CONCAT(YEAR(`awaysince`), '-01-01') WHERE MONTH(`awaysince`) = 0;
UPDATE `band_lookup` SET `awaysince_precision` = 'month', `awaysince` = CONCAT(YEAR(`awaysince`), '-', LPAD(MONTH(`awaysince`), 2, '0'), '-01') WHERE DAY(`awaysince`) = 0;

ALTER TABLE `band_lookup`
  ADD CONSTRAINT `ck_band_lookup_insince_whole` CHECK (`insince` IS NULL OR (MONTH(`insince`) > 0 AND DAY(`insince`) > 0)),
  ADD CONSTRAINT `ck_band_lookup_awaysince_whole` CHECK (`awaysince` IS NULL OR (MONTH(`awaysince`) > 0 AND DAY(`awaysince`) > 0));
