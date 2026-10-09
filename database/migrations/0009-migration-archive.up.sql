-- 0009 migration-archive: up. Where a migration keeps the rows it deletes or changes, so its
-- down puts them back exactly (decided on #57). The clean-ups of #57, #64, #65 and #66 remove
-- duplicates, merge a table and correct values; a down that could not restore what its up
-- removed would not be a working down.
--
-- The rows stay in the database rather than in the migration files: the same migration runs on
-- production and on the test fixtures, whose rows differ, and the repository is public while
-- some of these rows (ratings, collections) record what people did.
--
-- One row per archived row: the migration's version, the table, what the up did to it, and the
-- row itself as JSON (a deleted or changed row as it was, an inserted row as it went in). A down
-- reads its own version's rows back and deletes them. Nothing else writes here.
CREATE TABLE `migration_archive` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` char(4) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `action` enum('deleted','inserted','changed') NOT NULL,
  `row_data` json NOT NULL,
  `added` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `i_migration_archive_version` (`version`, `table_name`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
