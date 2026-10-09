-- 0027 news-expiry: up. A news item that never expires has expires NULL (#88), as 1 651 of
-- them on production already did on 2026-10-09; the other 108 had 0000-00-00 00:00:00, which
-- nothing reads. They are archived, since the column was nullable and NULL alone would not say
-- which were zeros, and a CHECK refuses zero parts from now on.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0027', 'news', 'changed', JSON_OBJECT('ID', `ID`, 'expires', CAST(`expires` AS CHAR))
  FROM `news`
 WHERE MONTH(`expires`) = 0 OR DAY(`expires`) = 0;

UPDATE `news` SET `expires` = NULL WHERE MONTH(`expires`) = 0 OR DAY(`expires`) = 0;

ALTER TABLE `news` ADD CONSTRAINT `ck_news_expires_whole` CHECK (`expires` IS NULL OR (MONTH(`expires`) > 0 AND DAY(`expires`) > 0));
