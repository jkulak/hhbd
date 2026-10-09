-- 0013 feattypes: down. Drops the unique key, takes the inferred roles off the credits again,
-- and brings back the deleted test row.
ALTER TABLE `feattypes` DROP KEY `u_feattypes`;

UPDATE `feature_lookup` z
  JOIN `migration_archive` m
    ON m.`version` = '0013' AND m.`table_name` = 'feature_lookup' AND m.`action` = 'changed'
   AND z.`songid` = JSON_VALUE(m.`row_data`, '$.songid') AND z.`artistid` = JSON_VALUE(m.`row_data`, '$.artistid')
   AND z.`feattype` = JSON_VALUE(m.`row_data`, '$.to')
   SET z.`feattype` = 0;

INSERT INTO `feattypes` (`id`, `feattype`, `status`)
SELECT JSON_VALUE(`row_data`, '$.id'), JSON_VALUE(`row_data`, '$.feattype'), JSON_VALUE(`row_data`, '$.status')
  FROM `migration_archive`
 WHERE `version` = '0013' AND `table_name` = 'feattypes' AND `action` = 'deleted'
 ORDER BY `id`;

DELETE FROM `migration_archive` WHERE `version` = '0013';
