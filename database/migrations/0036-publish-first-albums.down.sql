-- 0036 publish-first-albums: down. The albums the up published get back the status they had,
-- from migration_archive (0009).
UPDATE `albums` a
  JOIN `migration_archive` m
    ON m.`version` = '0036' AND m.`table_name` = 'albums' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET a.`status` = JSON_VALUE(m.`row_data`, '$.status');
DELETE FROM `migration_archive` WHERE `version` = '0036';
