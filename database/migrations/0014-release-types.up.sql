-- 0014 release-types: up. What kind of release an album is, and whether it came out digitally
-- (#53). Until now two columns hinted at the type: singiel (69 rows, used until 2005) and epfor
-- (44 rows, the album an EP or single preceded, used until 2008), and nothing could say
-- mixtape, beat tape or compilation. media_cd, media_mc and media_lp could not say "digital",
-- so 872 albums are "CD only" because nothing else fitted. About a third of what came out in
-- 2016 and 2017 is an EP, a mixtape or digital-only, so the import needs these first.
--
-- An album flagged singiel or epfor becomes a single when it has one to three tracks and an
-- EP otherwise; every other album stays an album. singiel and epfor stay, and the page reads
-- release_type. The columns are new, so the down only drops them.
ALTER TABLE `albums`
  ADD COLUMN `release_type` enum('album','ep','mixtape','compilation','beat_tape','single','other') NOT NULL DEFAULT 'album' AFTER `title`,
  ADD COLUMN `media_digital` tinyint(1) NOT NULL DEFAULT 0 AFTER `catalog_lp`,
  ADD COLUMN `catalog_digital` tinytext DEFAULT NULL AFTER `media_digital`;

UPDATE `albums` a
   SET a.`release_type` = IF((SELECT COUNT(*) FROM `album_lookup` l WHERE l.`albumid` = a.`id`) BETWEEN 1 AND 3, 'single', 'ep')
 WHERE a.`singiel` = 1 OR (a.`epfor` IS NOT NULL AND a.`epfor` <> 0);
