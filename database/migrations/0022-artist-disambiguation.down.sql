-- 0022 artist-disambiguation: down. The unique key goes back to the name alone, so an artist
-- with a qualifier takes it into the name, as the catalogue would have had to write it before:
-- "Solar (SBM Label)". Every such artist is archived (migration_archive, 0009), and the up
-- splits the name again.
--
-- First the names the down would leave, under the catalogue's collation, into a table that
-- refuses a duplicate or one too long for artists.name: a folded name another artist already
-- has stops the down here, before anything has changed, with the name in the error.
CREATE TEMPORARY TABLE `migration_0022_names` (
  `name` varchar(250) NOT NULL,
  UNIQUE KEY (`name`)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
INSERT INTO `migration_0022_names` (`name`)
SELECT IF(`disambiguation` = '', `name`, CONCAT(`name`, ' (', `disambiguation`, ')'))
  FROM `artists`
 WHERE `name` IS NOT NULL;
DROP TEMPORARY TABLE `migration_0022_names`;

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0022', 'artists', 'changed', JSON_OBJECT(
         'id', `id`, 'name', `name`, 'disambiguation', `disambiguation`,
         'folded', CONCAT(`name`, ' (', `disambiguation`, ')'))
  FROM `artists`
 WHERE `disambiguation` <> '';
UPDATE `artists`
   SET `name` = CONCAT(`name`, ' (', `disambiguation`, ')')
 WHERE `disambiguation` <> '';
ALTER TABLE `artists`
  DROP KEY `u_artists_name`,
  ADD UNIQUE KEY `name` (`name`),
  DROP COLUMN `disambiguation`;
