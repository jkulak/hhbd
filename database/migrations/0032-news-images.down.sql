-- 0032 news-images: down. Each news item's image name back as it was, from the archive.
UPDATE `news` n
  JOIN `migration_archive` m
    ON m.`version` = '0032' AND m.`table_name` = 'news' AND m.`action` = 'changed'
   AND n.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET n.`graph` = JSON_VALUE(m.`row_data`, '$.graph');

DELETE FROM `migration_archive` WHERE `version` = '0032';
