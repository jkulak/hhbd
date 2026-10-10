-- ============================================
-- HHBD Test Fixtures for the tables migrations added
-- ============================================
-- Loaded by `make reset-db` after every migration has run, for tables the baseline does not
-- have, so fixtures.sql (written for the baseline) cannot fill them. Keep it to what the
-- smoke test and the schema test need.
-- ============================================

-- An import run, so provenance rows have one to point at (#52).
INSERT INTO `import_runs` (`id`, `batch`, `batch_sha256`, `mode`, `started`, `finished`, `created_count`) VALUES
(1, 'fixtures.ndjson', REPEAT('f', 64), 'apply', '2026-10-09 12:00:00', '2026-10-09 12:01:00', 2);

-- Superextra's cover came through Discogs's API, which asks for "Data provided by Discogs." on
-- the page (#62); Jestem Hip Hopem's title came from the CC0 dump, which asks for nothing.
-- Mes's profile came through the API too.
INSERT INTO `import_provenance` (`entity_type`, `entity_id`, `field`, `source`, `source_ref`, `licence`, `fetched`, `run_id`) VALUES
('album', 535, 'cover', 'discogs', 'https://www.discogs.com/release/1234567', NULL, '2026-10-09 12:00:00', 1),
('album', 1, 'title', 'discogs', 'discogs:master:7654321', 'CC0', '2026-10-09 12:00:00', 1),
('artist', 35, 'profile', 'discogs', 'https://www.discogs.com/artist/271903', NULL, '2026-10-09 12:00:00', 1);

-- Ids the pages link to: the Discogs pages the credit points at, and a Deezer and an Apple
-- Music album to listen to (#51, #62).
INSERT INTO `external_ids` (`entity_type`, `entity_id`, `source`, `kind`, `value`) VALUES
('album', 535, 'discogs', 'release', '1234567'),
('album', 535, 'deezer', 'album', '302127'),
('album', 535, 'itunes', 'collection', '1440857781'),
('album', 1, 'discogs', 'master', '7654321'),
('artist', 35, 'discogs', 'artist', '271903');

-- Mes's photos as a gallery (#61): his main photo gets the credit a Commons licence asks for, and a
-- second photo, cropped, goes below it with its own.
UPDATE `artists_photos` SET `credit` = 'Jan Kowalski', `licence` = 'CC BY-SA 4.0',
       `licence_url` = 'https://creativecommons.org/licenses/by-sa/4.0/',
       `sourceurl` = 'https://commons.wikimedia.org/wiki/File:Mes_test.jpg'
 WHERE `artistid` = 35 AND `main` = 'y';
INSERT INTO `artists_photos` (`artistid`, `filename`, `description`, `main`, `source`, `sourceurl`, `licence`, `licence_url`, `credit`, `modified`, `addedby`) VALUES
(35, 'test-artist-030.jpg', 'Mes na koncercie', 'n', 'commons', 'https://commons.wikimedia.org/wiki/File:Mes_koncert.jpg', 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0/', 'Anna Nowak', 1, 1100);

-- Two artists of one name, told apart by their qualifiers (#102): each has its own page, title
-- and slug, a list holding both shows the qualifiers, and the band one of them is in lists him
-- by his name alone.
INSERT INTO `artists` (`id`, `name`, `disambiguation`, `urlname`, `type`, `trivia`, `website`, `status`, `viewed`) VALUES
(64, 'Solar', 'SBM Label', 'solar-sbm-label', 'm', '', '', 999, 900),
(65, 'Solar', 'raper z Poznania', 'solar-raper-z-poznania', 'm', '', '', 999, 100),
(66, 'Skład Solara', '', 'sklad-solara', 'b', '', '', 999, 50),
-- Names whose letters are not Polish (#155): Cyrillic, which left an empty slug and a 404,
-- and a Latin name with an umlaut, which lost the letter
(67, 'Игроки Улиц', '', 'igroki-ulic', 'b', '', '', 999, 40),
(68, 'Wöyza', '', 'woyza', 'm', '', '', 999, 30);
INSERT INTO `band_lookup` (`artistid`, `bandid`, `status`) VALUES
(64, 66, 999);

-- One open review item of each reason (#103), for the panel an admin sees on the page and for
-- the list: Solar from Poznań may be the SBM one, Superextra's cover is a stand-in, the
-- sources disagree on Podmiejski Gwar's date and Muzyka Poważna's type, and only Discogs
-- knows Morska Bryza.
INSERT INTO `review_items` (`id`, `entity_type`, `entity_id`, `reason`, `detail`, `run_id`, `created`) VALUES
(1, 'artist', 65, 'namesake', '{"text": "same name as hhbd artist 64", "suggestions": [64]}', 1, '2026-10-09 12:00:00'),
(2, 'album', 535, 'cover_placeholder', '{"text": "discogs, 600 × 600 px"}', 1, '2026-10-09 12:00:01'),
(3, 'album', 50, 'date_disputed', '{"text": "Discogs says 2013, MusicBrainz 2013-05-17", "values": ["2013", "2013-05-17"]}', 1, '2026-10-09 12:00:02'),
(4, 'album', 2, 'type_disputed', '{"values": ["single", "ep"]}', 1, '2026-10-09 12:00:03'),
(5, 'album', 46, 'single_source', '{"text": "only Discogs knows it"}', 1, '2026-10-09 12:00:04');

-- The admin's password, adminpass, as password_hash() makes it (#41), which needs 0034's wider
-- column; the tests log in with it.
UPDATE `hhb_users` SET `usr_password` = '$2y$12$sxdWRc7n3UH1A8DiXi7iyutjX4TF22J4e/Tlgy99UZ5bQeSDDUZia' WHERE `usr_id` = 10;

-- Main photos that are not square (#166): Eldo's landscape, as production's is, and Stasiak's
-- portrait; the page keeps their proportions with the longer side at 300 px.
UPDATE `artists_photos` SET `width` = 600, `height` = 378 WHERE `artistid` = 2 AND `main` = 'y';
UPDATE `artists_photos` SET `width` = 225, `height` = 300 WHERE `artistid` = 3 AND `main` = 'y';

-- An album an import added but did not publish (#168): Mes's, with a year alone and no label,
-- and a song nowhere else. A visitor finds neither, nor their pages; an admin opens the album.
INSERT INTO `albums` (`id`, `title`, `urlname`, `labelid`, `year`, `release_date_precision`, `legal`, `cover`, `premier`, `artistabout`, `addedby`, `status`, `viewed`) VALUES
(779, 'Taśma Robocza', 'tasma-robocza', NULL, '2016-01-01', 'year', 'y', '', '', '', 1100, 0, 0);
INSERT INTO `album_artist_lookup` (`albumid`, `artistid`, `role`, `position`, `status`) VALUES (779, 35, 'main', 1, 999);
INSERT INTO `songs` (`id`, `title`, `urlname`, `lyrics`, `addedby`, `status`, `viewed`) VALUES
(9101, 'Szkic Numer Jeden', 'szkic-numer-jeden', '', 1100, 999, 0);
INSERT INTO `album_lookup` (`songid`, `albumid`, `disc`, `track`, `status`) VALUES (9101, 779, 1, 1, 999);
INSERT INTO `artist_lookup` (`songid`, `artistid`, `status`) VALUES (9101, 35, 999);

-- What it lacks, for the admin (#168), and a self-release with all it needs: published, with
-- "wydanie własne" where a label would be.
INSERT INTO `review_items` (`id`, `entity_type`, `entity_id`, `reason`, `detail`, `run_id`, `created`) VALUES
(8, 'album', 779, 'incomplete', '{"text": "brak: wytwórnia, data dzienna"}', 1, '2026-10-10 12:00:00');
INSERT INTO `albums` (`id`, `title`, `urlname`, `labelid`, `self_released`, `year`, `release_date_precision`, `legal`, `cover`, `premier`, `artistabout`, `addedby`, `status`, `viewed`) VALUES
(780, 'Własnym Sumptem', 'wlasnym-sumptem', NULL, 1, '2016-03-18', 'day', 'y', '', '', '', 1100, 999, 0);
INSERT INTO `album_artist_lookup` (`albumid`, `artistid`, `role`, `position`, `status`) VALUES (780, 68, 'main', 1, 999);
INSERT INTO `songs` (`id`, `title`, `urlname`, `lyrics`, `addedby`, `status`, `viewed`) VALUES
(9102, 'Domowa Produkcja', 'domowa-produkcja', '', 1100, 999, 0);
INSERT INTO `album_lookup` (`songid`, `albumid`, `disc`, `track`, `status`) VALUES (9102, 780, 1, 1, 999);
