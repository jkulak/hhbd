-- 0015 album-credits: down. Drops the three columns; the credits themselves stay.
ALTER TABLE `album_artist_lookup` DROP COLUMN `credited_as`, DROP COLUMN `position`, DROP COLUMN `role`;
