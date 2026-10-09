-- 0017 no-label: up. An album without a label has labelid NULL (#55). The catalog could not say
-- that until the lists joined labels with LEFT JOIN, so 247 albums on production point at a
-- placeholder label, 27 "BRAK" ("none"), which the pages hid from every list. Self-released
-- records are the largest group the import brings, so "no label" has to be NULL, not a label.
--
-- The release before this one reads both NULL and "BRAK" as no label. The label and each
-- album's old labelid are archived (migration_archive, 0009), and the down puts both back.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0017', 'labels', 'deleted', JSON_OBJECT(
         'id', `id`, 'name', `name`, 'urlname', `urlname`, 'website', `website`, 'email', `email`,
         'addres', `addres`, 'addedby', `addedby`, 'added', `added`, 'updatedby', `updatedby`,
         'updated', `updated`, 'viewed', `viewed`, 'status', `status`, 'profile', `profile`,
         'logo', `logo`, 'hits', `hits`)
  FROM `labels`
 WHERE `id` = 27 AND `name` = 'BRAK';

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0017', 'albums', 'changed', JSON_OBJECT('id', a.`id`, 'labelid', a.`labelid`)
  FROM `albums` a
  JOIN `labels` l ON l.`id` = a.`labelid`
 WHERE l.`id` = 27 AND l.`name` = 'BRAK';

UPDATE `albums` a
  JOIN `migration_archive` m
    ON m.`version` = '0017' AND m.`table_name` = 'albums' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET a.`labelid` = NULL;

DELETE l FROM `labels` l
  JOIN `migration_archive` m
    ON m.`version` = '0017' AND m.`table_name` = 'labels' AND m.`action` = 'deleted'
   AND l.`id` = JSON_VALUE(m.`row_data`, '$.id');
