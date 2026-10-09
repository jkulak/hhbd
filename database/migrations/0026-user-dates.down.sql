-- 0026 user-dates: down. Every NULL back to the zero time the NOT NULL columns held, and the
-- columns NOT NULL again. migrate.sh runs it in a session that allows zero dates.
ALTER TABLE `hhb_users`
  DROP CONSTRAINT `ck_hhb_users_added_whole`,
  DROP CONSTRAINT `ck_hhb_users_updated_whole`,
  DROP CONSTRAINT `ck_hhb_users_last_login_whole`;

UPDATE `hhb_users` SET `usr_added` = '0000-00-00 00:00:00' WHERE `usr_added` IS NULL;
UPDATE `hhb_users` SET `usr_updated` = '0000-00-00 00:00:00' WHERE `usr_updated` IS NULL;
UPDATE `hhb_users` SET `usr_last_login` = '0000-00-00 00:00:00' WHERE `usr_last_login` IS NULL;

ALTER TABLE `hhb_users`
  MODIFY `usr_added` datetime NOT NULL,
  MODIFY `usr_updated` datetime NOT NULL,
  MODIFY `usr_last_login` datetime NOT NULL;
