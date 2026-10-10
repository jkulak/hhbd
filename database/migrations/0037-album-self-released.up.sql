-- 0037 album-self-released: up. A release its artists put out themselves, with no label:
-- "wydanie własne", a nielegal (#168). Until now a self-release and a release whose label
-- nobody knows were both labelid NULL, and only the first is complete enough to publish.
ALTER TABLE `albums` ADD COLUMN `self_released` tinyint(1) NOT NULL DEFAULT 0 AFTER `labelid`;

-- The down puts the self-releases' ids aside (migration_archive, 0009); they get the flag back.
UPDATE `albums` a
  JOIN `migration_archive` m
    ON m.`version` = '0037' AND m.`table_name` = 'albums' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET a.`self_released` = 1;
DELETE FROM `migration_archive` WHERE `version` = '0037';
