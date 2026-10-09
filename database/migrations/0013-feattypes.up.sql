-- 0013 feattypes: up. Credits arrive from the import as role names (Rap, Śpiew, Cuty, ...) and
-- have to land on one feattypes row each (#66), so a role name is now unique, the test row
-- nobody uses goes, and credits stored without a role get one where the data says which.
--
-- The row with id 0 and no name stays: feature_lookup joins it for every credit without a
-- role (579 on production on 2026-10-09), and the song page would lose those credits without
-- it.
--
-- A credit without a role takes the artist's role only when every other credit of that artist
-- has the same one: on production 223 of the 579 (149 Rap, 53 Śpiew, 10 Cuty, 7 Bitbox, four
-- others). The other 356 stay without a role: 296 artists have no other credit and 60 have
-- several roles, so any role given them would be a guess, and "Rap" for all would have called
-- 53 singers and 10 DJs rappers. A credit whose artist already has that role on the same song
-- is left alone too, since the unique key of 0011 would refuse it. Everything changed or
-- deleted is archived (migration_archive, 0009) and the down puts it back.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0013', 'feattypes', 'deleted', JSON_OBJECT('id', f.`id`, 'feattype', f.`feattype`, 'status', f.`status`)
  FROM `feattypes` f
 WHERE f.`feattype` = 'testest'
   AND NOT EXISTS (SELECT 1 FROM `feature_lookup` l WHERE l.`feattype` = f.`id`);

DELETE f FROM `feattypes` f
  JOIN `migration_archive` m
    ON m.`version` = '0013' AND m.`table_name` = 'feattypes' AND m.`action` = 'deleted'
   AND f.`id` = JSON_VALUE(m.`row_data`, '$.id');

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0013', 'feature_lookup', 'changed', JSON_OBJECT('songid', x.`songid`, 'artistid', x.`artistid`, 'feattype', 0, 'to', x.`role`)
  FROM (SELECT z.`songid`, z.`artistid`, MIN(o.`feattype`) AS `role`
          FROM `feature_lookup` z
          JOIN `feature_lookup` o ON o.`artistid` = z.`artistid` AND o.`feattype` <> 0
         WHERE z.`feattype` = 0
         GROUP BY z.`songid`, z.`artistid`
        HAVING COUNT(DISTINCT o.`feattype`) = 1) x
 WHERE NOT EXISTS (SELECT 1 FROM `feature_lookup` e WHERE e.`songid` = x.`songid` AND e.`artistid` = x.`artistid` AND e.`feattype` = x.`role`);

UPDATE `feature_lookup` z
  JOIN `migration_archive` m
    ON m.`version` = '0013' AND m.`table_name` = 'feature_lookup' AND m.`action` = 'changed'
   AND z.`songid` = JSON_VALUE(m.`row_data`, '$.songid') AND z.`artistid` = JSON_VALUE(m.`row_data`, '$.artistid')
   AND z.`feattype` = 0
   SET z.`feattype` = JSON_VALUE(m.`row_data`, '$.to');

-- Under the tables' collation, so "rap" and "Rap" are one role.
ALTER TABLE `feattypes` ADD UNIQUE KEY `u_feattypes` (`feattype`(64));
