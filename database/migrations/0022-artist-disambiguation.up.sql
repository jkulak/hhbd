-- 0022 artist-disambiguation: up. Two artists may share a name, told apart by a qualifier
-- (#102): "Solar (SBM Label)" next to "Solar (raper z Poznania)". The content project meets
-- such names in every year it reads, 71 in 2016 alone, and the unique key on the name alone
-- left it no way to write down "this is somebody else".
--
-- The qualifier is NOT NULL with '' for none, so the unique key still holds a name without a
-- qualifier to one row: in a unique key NULLs never clash, so a NULL qualifier would have let
-- any number of plain "Solar"s in.
ALTER TABLE `artists`
  ADD COLUMN `disambiguation` varchar(190) NOT NULL DEFAULT '' AFTER `name`,
  DROP KEY `name`,
  ADD UNIQUE KEY `u_artists_name` (`name`, `disambiguation`);

-- The down folds each qualifier into its name and archives the pair (migration_archive,
-- 0009); an artist that still has the folded name gets its name and qualifier back.
UPDATE `artists` a
  JOIN `migration_archive` m
    ON m.`version` = '0022' AND m.`table_name` = 'artists' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   AND a.`name` = JSON_VALUE(m.`row_data`, '$.folded')
   SET a.`name` = JSON_VALUE(m.`row_data`, '$.name'),
       a.`disambiguation` = JSON_VALUE(m.`row_data`, '$.disambiguation');
DELETE FROM `migration_archive` WHERE `version` = '0022';
