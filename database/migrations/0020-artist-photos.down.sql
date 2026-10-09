-- 0020 artist-photos: down. Drops the key and the new columns and makes artistid a smallint
-- again; no artist id on production comes near its limit.
ALTER TABLE `artists_photos`
  DROP KEY `u_artists_photos`,
  DROP COLUMN `modified`, DROP COLUMN `credit`, DROP COLUMN `licence_url`, DROP COLUMN `licence`,
  DROP COLUMN `mime`, DROP COLUMN `sha256`, DROP COLUMN `height`, DROP COLUMN `width`,
  MODIFY `artistid` smallint(6) NOT NULL DEFAULT 0;
