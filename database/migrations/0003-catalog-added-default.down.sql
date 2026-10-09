-- 0003 catalog-added-default: down. Back to no default.
-- MODIFY, restating the baseline's column, rather than ALTER COLUMN ... SET DEFAULT: MariaDB
-- 10.11 ignores that, with no error, when the current_timestamp() came with CREATE TABLE, as it
-- does in a database loaded from a dump (a restored backup, or make reset-db's kept result).
ALTER TABLE `albums` MODIFY `added` datetime DEFAULT NULL;
ALTER TABLE `artists` MODIFY `added` datetime DEFAULT NULL;
ALTER TABLE `songs` MODIFY `added` datetime DEFAULT NULL;
ALTER TABLE `labels` MODIFY `added` datetime DEFAULT NULL;
ALTER TABLE `cities` MODIFY `added` datetime DEFAULT NULL;
ALTER TABLE `news` MODIFY `added` datetime DEFAULT NULL;
