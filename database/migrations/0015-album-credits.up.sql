-- 0015 album-credits: up. An album can be credited to several artists (#58): Białas & Lanek,
-- Taco Hemingway & Dawid Podsiadło, and the duo records the import holds back until this
-- exists. Each credit says whether the artist is a main or a featured one, its place in the
-- credit line, and the name it was credited under when that differs from the artist's own.
--
-- The pages read these columns when they are there and order credits by artist id when they
-- are not, so this can run before or after the release that reads them. The albums that
-- already have several artists (two on production on 2026-10-09) get positions in artist id
-- order, the order the pages have always shown them in. The columns are new, so the down only
-- drops them.
ALTER TABLE `album_artist_lookup`
  ADD COLUMN `role` enum('main','featured') NOT NULL DEFAULT 'main' AFTER `artistid`,
  ADD COLUMN `position` tinyint(4) NOT NULL DEFAULT 1 AFTER `role`,
  ADD COLUMN `credited_as` tinytext DEFAULT NULL AFTER `position`;

UPDATE `album_artist_lookup` l
  JOIN (SELECT `albumid`, `artistid`, ROW_NUMBER() OVER (PARTITION BY `albumid` ORDER BY `artistid`) AS `n`
          FROM `album_artist_lookup`) r
    ON r.`albumid` = l.`albumid` AND r.`artistid` = l.`artistid`
   SET l.`position` = r.`n`;
