-- 0017 no-label: down. Brings label 27 back as it was and points its albums at it again.
INSERT INTO `labels` (`id`, `name`, `urlname`, `website`, `email`, `addres`, `addedby`, `added`,
                      `updatedby`, `updated`, `viewed`, `status`, `profile`, `logo`, `hits`)
SELECT JSON_VALUE(`row_data`, '$.id'), JSON_VALUE(`row_data`, '$.name'), JSON_VALUE(`row_data`, '$.urlname'),
       JSON_VALUE(`row_data`, '$.website'), JSON_VALUE(`row_data`, '$.email'), JSON_VALUE(`row_data`, '$.addres'),
       JSON_VALUE(`row_data`, '$.addedby'), JSON_VALUE(`row_data`, '$.added'), JSON_VALUE(`row_data`, '$.updatedby'),
       JSON_VALUE(`row_data`, '$.updated'), JSON_VALUE(`row_data`, '$.viewed'), JSON_VALUE(`row_data`, '$.status'),
       JSON_VALUE(`row_data`, '$.profile'), JSON_VALUE(`row_data`, '$.logo'), JSON_VALUE(`row_data`, '$.hits')
  FROM `migration_archive`
 WHERE `version` = '0017' AND `table_name` = 'labels' AND `action` = 'deleted';

UPDATE `albums` a
  JOIN `migration_archive` m
    ON m.`version` = '0017' AND m.`table_name` = 'albums' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET a.`labelid` = JSON_VALUE(m.`row_data`, '$.labelid');

DELETE FROM `migration_archive` WHERE `version` = '0017';
