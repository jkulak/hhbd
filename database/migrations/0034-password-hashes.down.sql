-- 0034 password-hashes: down. Back to char(32), which holds an MD5 and nothing longer. An
-- account that has logged in since the up has a password_hash() there, which the column
-- cannot keep: it goes into migration_archive (0009), and the account has no password, so
-- nobody logs in with it, until the up puts the hash back.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0034', 'hhb_users', 'changed', JSON_OBJECT('usr_id', `usr_id`, 'usr_password', `usr_password`)
  FROM `hhb_users`
 WHERE CHAR_LENGTH(`usr_password`) > 32;
UPDATE `hhb_users` SET `usr_password` = '' WHERE CHAR_LENGTH(`usr_password`) > 32;

ALTER TABLE `hhb_users` MODIFY `usr_password` char(32) NOT NULL;
