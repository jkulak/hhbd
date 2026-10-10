-- 0037 album-self-released: down. The self-releases' ids go into migration_archive (0009), for
-- the up to flag them again, and the column goes.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0037', 'albums', 'changed', JSON_OBJECT('id', `id`)
  FROM `albums`
 WHERE `self_released` = 1;

ALTER TABLE `albums` DROP COLUMN `self_released`;
