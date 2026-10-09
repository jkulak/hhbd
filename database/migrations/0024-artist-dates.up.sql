-- 0024 artist-dates: up. An artist's start and end are whole dates or NULL (#88), as albums'
-- release dates became in 0018, so the server can run with NO_ZERO_DATE. On production on
-- 2026-10-09, 772 starts and 794 ends were 0000-00-00, which means not known and becomes NULL;
-- 15 and 1 were partial, 1998-00-00 or 1998-03-00, which become the first day of that year or
-- month with a precision, so a page can still say "since 1998". Every changed value is
-- archived (migration_archive, 0009): the column was nullable, so a NULL alone would not say
-- whether it was a zero date before. A CHECK refuses zero parts from now on, whatever a
-- session's sql_mode allows.
ALTER TABLE `artists`
  ADD COLUMN `since_precision` enum('day','month','year') NOT NULL DEFAULT 'day' AFTER `since`,
  ADD COLUMN `till_precision` enum('day','month','year') NOT NULL DEFAULT 'day' AFTER `till`;

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0024', 'artists', 'changed', JSON_OBJECT('id', `id`, 'since', CAST(`since` AS CHAR), 'till', CAST(`till` AS CHAR))
  FROM `artists`
 WHERE MONTH(`since`) = 0 OR DAY(`since`) = 0 OR MONTH(`till`) = 0 OR DAY(`till`) = 0;

UPDATE `artists` SET `since` = NULL WHERE `since` = '0000-00-00';
UPDATE `artists` SET `since_precision` = 'year', `since` = CONCAT(YEAR(`since`), '-01-01') WHERE MONTH(`since`) = 0;
UPDATE `artists` SET `since_precision` = 'month', `since` = CONCAT(YEAR(`since`), '-', LPAD(MONTH(`since`), 2, '0'), '-01') WHERE DAY(`since`) = 0;
UPDATE `artists` SET `till` = NULL WHERE `till` = '0000-00-00';
UPDATE `artists` SET `till_precision` = 'year', `till` = CONCAT(YEAR(`till`), '-01-01') WHERE MONTH(`till`) = 0;
UPDATE `artists` SET `till_precision` = 'month', `till` = CONCAT(YEAR(`till`), '-', LPAD(MONTH(`till`), 2, '0'), '-01') WHERE DAY(`till`) = 0;

ALTER TABLE `artists`
  ADD CONSTRAINT `ck_artists_since_whole` CHECK (`since` IS NULL OR (MONTH(`since`) > 0 AND DAY(`since`) > 0)),
  ADD CONSTRAINT `ck_artists_till_whole` CHECK (`till` IS NULL OR (MONTH(`till`) > 0 AND DAY(`till`) > 0));
