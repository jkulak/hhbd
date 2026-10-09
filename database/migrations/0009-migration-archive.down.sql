-- 0009 migration-archive: down. Drops the archive. The downs of the migrations after this one
-- run first and take their rows back out, so by now it is empty.
DROP TABLE IF EXISTS `migration_archive`;
