-- 0019 album-covers: up. Each album's cover files as rows with their variant, size, hash, source
-- and licence (#60). Until now albums.cover was a bare file name under content/a/, and the 75 px
-- thumbnail's name was derived from it, so nothing recorded where a cover came from, under what
-- terms or how big it is, and rows and files drifted apart unnoticed (#47). The import brings
-- covers from the Cover Art Archive and Discogs, each with a known source and licence.
--
-- One row per file: the album, the variant (orig, 600, 300 or 75 px), its path under content/,
-- width, height, SHA-256 and MIME type, where it came from and under what licence, whether it is
-- the album's main cover, and whether it is a stand-in to replace when a larger one turns up (a
-- 600 px Discogs cover shipped because nothing better existed, #96). A path rather than a bare name, so the covers already on the
-- volume (a/<name> and a/th/<name>-th.jpg) are described where they are; new variants go under
-- a/<variant>/. app/tools/covers.php fills the table from the files, as the database cannot
-- read them. albums.cover stays until every reader uses this table. The down drops the table.
CREATE TABLE `album_covers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `albumid` int(11) NOT NULL,
  `variant` enum('orig','600','300','75') NOT NULL,
  `path` varchar(190) NOT NULL,
  `width` smallint(5) unsigned NOT NULL,
  `height` smallint(5) unsigned NOT NULL,
  `sha256` char(64) NOT NULL,
  `mime` varchar(32) NOT NULL,
  `source` varchar(32) NOT NULL,
  `sourceurl` varchar(500) DEFAULT NULL,
  `licence` varchar(64) DEFAULT NULL,
  `main` enum('y','n') NOT NULL DEFAULT 'y',
  `needs_upgrade` tinyint(1) NOT NULL DEFAULT 0,
  `added` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_album_covers` (`albumid`, `variant`, `sha256`),
  KEY `i_album_covers_album` (`albumid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
