-- 0034 password-hashes: up. hhb_users.usr_password holds a password_hash() (#41), bcrypt's 60
-- characters now, and what a later default may need. The MD5s already in it stay as they are:
-- an account logs in with one once, and the login writes the new hash in its place.
ALTER TABLE `hhb_users` MODIFY `usr_password` varchar(255) NOT NULL;

-- The down puts aside the hashes the old column cannot hold (migration_archive, 0009); an
-- account still without a password gets its hash back.
UPDATE `hhb_users` u
  JOIN `migration_archive` m
    ON m.`version` = '0034' AND m.`table_name` = 'hhb_users' AND m.`action` = 'changed'
   AND u.`usr_id` = JSON_VALUE(m.`row_data`, '$.usr_id')
   AND u.`usr_password` = ''
   SET u.`usr_password` = JSON_VALUE(m.`row_data`, '$.usr_password');
DELETE FROM `migration_archive` WHERE `version` = '0034';
