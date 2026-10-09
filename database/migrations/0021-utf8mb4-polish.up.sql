-- 0021 utf8mb4-polish: up. The catalogue in utf8mb4 with the Polish collation (#71), before the
-- first import batch. utf8mb3 holds at most three bytes a character, so a name or title with an
-- emoji or another character outside the Basic Multilingual Plane could not be stored; and
-- utf8mb3_general_ci folds every Polish letter but ł onto its base letter, so "Żabson" equalled
-- "Zabson", and an import matching artists by name would have merged them. utf8mb4_polish_ci
-- tells them apart, stays case-insensitive ("żabson" is "Żabson") and sorts in Polish order.
--
-- Every table still in utf8mb3, 44 of them, is converted. CONVERT TO widens tinytext to text,
-- text to mediumtext and mediumtext to longtext, so each column keeps the number of characters
-- it could hold. The tables already in utf8mb4 stay as they are: external_ids, import_runs,
-- import_provenance, migration_archive and album_covers compare bytes on purpose, and
-- schema_migrations is the runner's own.
--
-- The down puts each table back to its old character set and collation and every character
-- column to its exact old definition (checked: SHOW CREATE TABLE is identical after up and
-- down). It fails rather than lose data once the catalogue holds what utf8mb3_general_ci cannot:
-- a 4-byte character, or two names a unique key keeps apart only by a Polish letter ("Zabson"
-- and "Żabson").
ALTER TABLE `albums` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `album_artist_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `album_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `album_prices` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `album_promomixes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `album_ratings` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `album_reviews` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `altnames_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `artists` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `artists_everyweek` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `artists_photos` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `artist_city_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `artist_concert_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `artist_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `band_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `cities` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `city_label_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `collection` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `feattypes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `feature_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `hhb_comments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `hhb_users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `hhb_user_lyrics_edit` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `labels` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `music_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `news` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `news_album_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `news_artist_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `news_city_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `news_concert_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `news_label_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `ratings` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `ratings_avg` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `remix_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `scratch_lookup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `searches` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `songs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `song_samples` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `submision_errors` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `submision_recommendations` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `users_activations` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `users_admins` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
ALTER TABLE `wishlist` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
