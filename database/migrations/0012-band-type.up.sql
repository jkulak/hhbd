-- 0012 band-type: up. An artist with members is a band, type 'b' (#65). The pages decide "band"
-- by members (Model_Artist_Container::isBand()) and show no type today, but type is what the
-- importer writes and what a page showing the label ("Projekt" for 'b') would read, and the
-- two disagreed: on production on 2026-10-09, 67 artists with members were typed 'x' (65) or
-- 'm' (2).
--
-- A member counts only when the member's own row exists, as it does for isBand(). The 66 'b'
-- artists without members stay 'b' and are listed on #65 for review: many are duos whose
-- members were never entered. Each changed type is archived (migration_archive, 0009) and the
-- down puts it back. updated and updatedby stay as they were: this is a data fix, not an edit.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0012', 'artists', 'changed', JSON_OBJECT('id', a.`id`, 'type', a.`type`)
  FROM `artists` a
 WHERE a.`type` <> 'b'
   AND a.`id` IN (SELECT b.`bandid` FROM `band_lookup` b JOIN `artists` m ON m.`id` = b.`artistid`);

UPDATE `artists` a
  JOIN `migration_archive` m
    ON m.`version` = '0012' AND m.`table_name` = 'artists' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET a.`type` = 'b';
