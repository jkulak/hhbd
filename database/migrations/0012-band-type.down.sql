-- 0012 band-type: down. Gives every artist the up changed its type back.
UPDATE `artists` a
  JOIN `migration_archive` m
    ON m.`version` = '0012' AND m.`table_name` = 'artists' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET a.`type` = JSON_VALUE(m.`row_data`, '$.type');

DELETE FROM `migration_archive` WHERE `version` = '0012';
