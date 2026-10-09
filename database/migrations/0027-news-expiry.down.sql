-- 0027 news-expiry: down. The zero expiry times back from the archive, and no CHECK.
-- migrate.sh runs it in a session that allows zero dates.
ALTER TABLE `news` DROP CONSTRAINT `ck_news_expires_whole`;

UPDATE `news` n
  JOIN `migration_archive` m
    ON m.`version` = '0027' AND m.`table_name` = 'news' AND m.`action` = 'changed'
   AND n.`ID` = JSON_VALUE(m.`row_data`, '$.ID')
   SET n.`expires` = JSON_VALUE(m.`row_data`, '$.expires');

DELETE FROM `migration_archive` WHERE `version` = '0027';
