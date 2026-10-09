-- 0033 admins-as-authors: down. The updatedby values the tools wrote back to the hhb_users ids
-- they were, from the archive; the rows the up added for an account, and the link, go. A row
-- userIdFor added after the up stays, with the updatedby values that name it.

UPDATE `albums` t
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'albums' AND m.`action` = 'changed'
   AND JSON_VALUE(m.`row_data`, '$.id') = t.`id`
   SET t.`updatedby` = JSON_VALUE(m.`row_data`, '$.updatedby');

UPDATE `artists` t
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'artists' AND m.`action` = 'changed'
   AND JSON_VALUE(m.`row_data`, '$.id') = t.`id`
   SET t.`updatedby` = JSON_VALUE(m.`row_data`, '$.updatedby');

UPDATE `labels` t
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'labels' AND m.`action` = 'changed'
   AND JSON_VALUE(m.`row_data`, '$.id') = t.`id`
   SET t.`updatedby` = JSON_VALUE(m.`row_data`, '$.updatedby');

UPDATE `songs` t
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'songs' AND m.`action` = 'changed'
   AND JSON_VALUE(m.`row_data`, '$.id') = t.`id`
   SET t.`updatedby` = JSON_VALUE(m.`row_data`, '$.updatedby');

UPDATE `cities` t
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'cities' AND m.`action` = 'changed'
   AND JSON_VALUE(m.`row_data`, '$.id') = t.`id`
   SET t.`updatedby` = JSON_VALUE(m.`row_data`, '$.updatedby');

DELETE u FROM `users` u
  JOIN `migration_archive` m ON m.`version` = '0033' AND m.`table_name` = 'users' AND m.`action` = 'inserted'
   AND JSON_VALUE(m.`row_data`, '$.hhb_usr_id') = u.`hhb_usr_id`;

ALTER TABLE `users` DROP KEY `u_users_hhb_usr_id`, DROP `hhb_usr_id`;

DELETE FROM `migration_archive` WHERE `version` = '0033';
