-- 0026 user-dates: up. A user's added, updated and last-login times are NULL when not known
-- (#88), not 0000-00-00 00:00:00. On production on 2026-10-09 every one of the 1 844 users had
-- a zero updated time (nothing writes it), 369 had never logged in, and 24 had no known
-- registration time.
--
-- The three columns were NOT NULL without a default, so a registration, which writes only
-- usr_added, was refused by the server's STRICT_TRANS_TABLES: they become nullable with no
-- default but NULL. Since they were NOT NULL, every NULL the up leaves was a zero, and the down
-- puts zeros back exactly by that rule; nothing needs archiving. A CHECK refuses zero parts.
ALTER TABLE `hhb_users`
  MODIFY `usr_added` datetime DEFAULT NULL,
  MODIFY `usr_updated` datetime DEFAULT NULL,
  MODIFY `usr_last_login` datetime DEFAULT NULL;

UPDATE `hhb_users` SET `usr_added` = NULL WHERE `usr_added` = '0000-00-00 00:00:00';
UPDATE `hhb_users` SET `usr_updated` = NULL WHERE `usr_updated` = '0000-00-00 00:00:00';
UPDATE `hhb_users` SET `usr_last_login` = NULL WHERE `usr_last_login` = '0000-00-00 00:00:00';

ALTER TABLE `hhb_users`
  ADD CONSTRAINT `ck_hhb_users_added_whole` CHECK (`usr_added` IS NULL OR (MONTH(`usr_added`) > 0 AND DAY(`usr_added`) > 0)),
  ADD CONSTRAINT `ck_hhb_users_updated_whole` CHECK (`usr_updated` IS NULL OR (MONTH(`usr_updated`) > 0 AND DAY(`usr_updated`) > 0)),
  ADD CONSTRAINT `ck_hhb_users_last_login_whole` CHECK (`usr_last_login` IS NULL OR (MONTH(`usr_last_login`) > 0 AND DAY(`usr_last_login`) > 0));
