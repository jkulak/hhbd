-- 0033 admins-as-authors: up. addedby and updatedby name one table, users (#132): the old table
-- the catalogue's history is in, the import's row (1100) among them. An admin logs in with an
-- hhb_users account, and the review panel (#103) and make edit (#115) wrote that account's id
-- into updatedby, a number that in users is someone else. users.hhb_usr_id links an admin's
-- account to the users row they edit as, and the tools write that row's ID
-- (Model_Audit_Api::userIdFor); an admin without one gets a row the first time.
--
-- On production the two admins are linked to the old accounts they built the catalogue with, as
-- Kuba decided on 2026-10-10: Kuba (account 67) is fee (1), Marcin Kaźmiruk (71) is muuody (3).
-- Named by id and name together, so another database, such as one built from the fixtures, is
-- not touched.
--
-- The updatedby values the tools already wrote, which their journal and the review items show,
-- become the linked row's ID. Archived (migration_archive, 0009); the down puts them back.
ALTER TABLE `users` ADD `hhb_usr_id` int(11) DEFAULT NULL, ADD UNIQUE KEY `u_users_hhb_usr_id` (`hhb_usr_id`);

UPDATE `users` u
  JOIN `hhb_users` h ON (u.`ID`, u.`login`, h.`usr_id`, h.`usr_display_name`) IN ((1, 'fee', 67, 'Kuba'), (3, 'muuody', 71, 'Marcin'))
   SET u.`hhb_usr_id` = h.`usr_id`;

-- Any other account the tools wrote for gets a users row of its own, as userIdFor would give it
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0033', 'users', 'inserted', JSON_OBJECT('hhb_usr_id', h.`usr_id`)
  FROM `hhb_users` h
 WHERE h.`usr_id` IN (SELECT `user_id` FROM `edit_operations` UNION SELECT `resolved_by` FROM `review_items` WHERE `resolved_by` IS NOT NULL)
   AND NOT EXISTS (SELECT 1 FROM `users` u WHERE u.`hhb_usr_id` = h.`usr_id`);

INSERT INTO `users` (`login`, `urlname`, `added`, `status`, `hhb_usr_id`)
SELECT LEFT(h.`usr_display_name`, 16), '', NOW(), 0, h.`usr_id`
  FROM `hhb_users` h
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'users' AND JSON_VALUE(m.`row_data`, '$.hhb_usr_id') = h.`usr_id`;

-- albums
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT DISTINCT '0033', 'albums', 'changed', JSON_OBJECT('id', t.`id`, 'updatedby', t.`updatedby`)
  FROM `albums` t
  JOIN `users` u ON u.`hhb_usr_id` = t.`updatedby`
 WHERE EXISTS (SELECT 1 FROM `edit_journal` j JOIN `edit_operations` o ON o.`id` = j.`operation_id`
                WHERE j.`table_name` = 'albums' AND j.`action` IN ('inserted', 'changed') AND o.`user_id` = t.`updatedby`
                  AND JSON_VALUE(j.`row_after`, '$.id') = t.`id` AND JSON_VALUE(j.`row_after`, '$.updatedby') = o.`user_id`)
    OR EXISTS (SELECT 1 FROM `review_items` r WHERE r.`entity_type` = 'album' AND r.`entity_id` = t.`id` AND r.`resolved_by` = t.`updatedby`);

UPDATE `albums` t
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'albums' AND m.`action` = 'changed'
   AND JSON_VALUE(m.`row_data`, '$.id') = t.`id`
  JOIN `users` u ON u.`hhb_usr_id` = JSON_VALUE(m.`row_data`, '$.updatedby')
   SET t.`updatedby` = u.`ID`;

-- artists
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT DISTINCT '0033', 'artists', 'changed', JSON_OBJECT('id', t.`id`, 'updatedby', t.`updatedby`)
  FROM `artists` t
  JOIN `users` u ON u.`hhb_usr_id` = t.`updatedby`
 WHERE EXISTS (SELECT 1 FROM `edit_journal` j JOIN `edit_operations` o ON o.`id` = j.`operation_id`
                WHERE j.`table_name` = 'artists' AND j.`action` IN ('inserted', 'changed') AND o.`user_id` = t.`updatedby`
                  AND JSON_VALUE(j.`row_after`, '$.id') = t.`id` AND JSON_VALUE(j.`row_after`, '$.updatedby') = o.`user_id`)
    OR EXISTS (SELECT 1 FROM `review_items` r WHERE r.`entity_type` = 'artist' AND r.`entity_id` = t.`id` AND r.`resolved_by` = t.`updatedby`);

UPDATE `artists` t
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'artists' AND m.`action` = 'changed'
   AND JSON_VALUE(m.`row_data`, '$.id') = t.`id`
  JOIN `users` u ON u.`hhb_usr_id` = JSON_VALUE(m.`row_data`, '$.updatedby')
   SET t.`updatedby` = u.`ID`;

-- labels
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT DISTINCT '0033', 'labels', 'changed', JSON_OBJECT('id', t.`id`, 'updatedby', t.`updatedby`)
  FROM `labels` t
  JOIN `users` u ON u.`hhb_usr_id` = t.`updatedby`
 WHERE EXISTS (SELECT 1 FROM `edit_journal` j JOIN `edit_operations` o ON o.`id` = j.`operation_id`
                WHERE j.`table_name` = 'labels' AND j.`action` IN ('inserted', 'changed') AND o.`user_id` = t.`updatedby`
                  AND JSON_VALUE(j.`row_after`, '$.id') = t.`id` AND JSON_VALUE(j.`row_after`, '$.updatedby') = o.`user_id`)
    OR EXISTS (SELECT 1 FROM `review_items` r WHERE r.`entity_type` = 'label' AND r.`entity_id` = t.`id` AND r.`resolved_by` = t.`updatedby`);

UPDATE `labels` t
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'labels' AND m.`action` = 'changed'
   AND JSON_VALUE(m.`row_data`, '$.id') = t.`id`
  JOIN `users` u ON u.`hhb_usr_id` = JSON_VALUE(m.`row_data`, '$.updatedby')
   SET t.`updatedby` = u.`ID`;

-- songs
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT DISTINCT '0033', 'songs', 'changed', JSON_OBJECT('id', t.`id`, 'updatedby', t.`updatedby`)
  FROM `songs` t
  JOIN `users` u ON u.`hhb_usr_id` = t.`updatedby`
 WHERE EXISTS (SELECT 1 FROM `edit_journal` j JOIN `edit_operations` o ON o.`id` = j.`operation_id`
                WHERE j.`table_name` = 'songs' AND j.`action` IN ('inserted', 'changed') AND o.`user_id` = t.`updatedby`
                  AND JSON_VALUE(j.`row_after`, '$.id') = t.`id` AND JSON_VALUE(j.`row_after`, '$.updatedby') = o.`user_id`)
    OR EXISTS (SELECT 1 FROM `review_items` r WHERE r.`entity_type` = 'song' AND r.`entity_id` = t.`id` AND r.`resolved_by` = t.`updatedby`);

UPDATE `songs` t
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'songs' AND m.`action` = 'changed'
   AND JSON_VALUE(m.`row_data`, '$.id') = t.`id`
  JOIN `users` u ON u.`hhb_usr_id` = JSON_VALUE(m.`row_data`, '$.updatedby')
   SET t.`updatedby` = u.`ID`;

-- cities
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT DISTINCT '0033', 'cities', 'changed', JSON_OBJECT('id', t.`id`, 'updatedby', t.`updatedby`)
  FROM `cities` t
  JOIN `users` u ON u.`hhb_usr_id` = t.`updatedby`
 WHERE EXISTS (SELECT 1 FROM `edit_journal` j JOIN `edit_operations` o ON o.`id` = j.`operation_id`
                WHERE j.`table_name` = 'cities' AND j.`action` IN ('inserted', 'changed') AND o.`user_id` = t.`updatedby`
                  AND JSON_VALUE(j.`row_after`, '$.id') = t.`id` AND JSON_VALUE(j.`row_after`, '$.updatedby') = o.`user_id`);

UPDATE `cities` t
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'cities' AND m.`action` = 'changed'
   AND JSON_VALUE(m.`row_data`, '$.id') = t.`id`
  JOIN `users` u ON u.`hhb_usr_id` = JSON_VALUE(m.`row_data`, '$.updatedby')
   SET t.`updatedby` = u.`ID`;
