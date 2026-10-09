-- 0020 artist-photos: up. Artist photos as a gallery with a credit and a licence each (#61).
-- artists_photos allowed several photos per artist (120 rows for 115 artists on production) but
-- knew nothing of a file's size, type or hash, nor its licence; source and sourceurl are free
-- text. The import brings photos from Wikimedia Commons under CC BY and CC BY-SA licences, which
-- require the author, the licence with a link, and a note of any change (a crop, a resize) next
-- to the photo.
--
-- artistid becomes an int like every other id; a smallint stops at 32 767. The file's width,
-- height, SHA-256 and MIME type come from app/tools/photos.php, as the database cannot read
-- files; licence, licence_url, credit and modified from whoever adds the photo. One artist and
-- one file appear once. The new columns start empty, so the down drops them and restores the
-- smallint.
ALTER TABLE `artists_photos`
  MODIFY `artistid` int(11) NOT NULL DEFAULT 0,
  ADD COLUMN `width` smallint(5) unsigned DEFAULT NULL AFTER `filename`,
  ADD COLUMN `height` smallint(5) unsigned DEFAULT NULL AFTER `width`,
  ADD COLUMN `sha256` char(64) DEFAULT NULL AFTER `height`,
  ADD COLUMN `mime` varchar(32) DEFAULT NULL AFTER `sha256`,
  ADD COLUMN `licence` varchar(64) DEFAULT NULL AFTER `sourceurl`,
  ADD COLUMN `licence_url` varchar(500) DEFAULT NULL AFTER `licence`,
  ADD COLUMN `credit` varchar(255) DEFAULT NULL AFTER `licence_url`,
  ADD COLUMN `modified` tinyint(1) NOT NULL DEFAULT 0 AFTER `credit`,
  ADD UNIQUE KEY `u_artists_photos` (`artistid`, `sha256`);
