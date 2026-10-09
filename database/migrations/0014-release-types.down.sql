-- 0014 release-types: down. Drops the three columns; singiel and epfor still hold what they held.
ALTER TABLE `albums` DROP COLUMN `catalog_digital`, DROP COLUMN `media_digital`, DROP COLUMN `release_type`;
