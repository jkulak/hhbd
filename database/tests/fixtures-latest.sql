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
