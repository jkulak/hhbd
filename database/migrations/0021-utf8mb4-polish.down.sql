-- 0021 utf8mb4-polish: down. Each table back to the character set and collation it had, and
-- every character column to its exact old type, character set, collation, nullability and
-- default, as SHOW CREATE TABLE showed them before the up. Generated from that schema.

ALTER TABLE `albums` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `legal` enum('y','n') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'y',
  MODIFY `title` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `release_type` enum('album','ep','mixtape','compilation','beat_tape','single','other') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'album',
  MODIFY `urlname` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `release_date_precision` enum('day','month','year') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'day',
  MODIFY `premier` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `catalog_mc` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `catalog_cd` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `catalog_lp` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `catalog_digital` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `cover` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `artistabout` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `notes` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `album_artist_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `role` enum('main','featured') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'main',
  MODIFY `credited_as` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `album_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `album_prices` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `link` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL;

ALTER TABLE `album_promomixes` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `urlname` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  MODIFY `promomix` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `size` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL;

ALTER TABLE `album_ratings` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `album_reviews` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `title` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `review` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `altnames_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `altname` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `artists` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `name` varchar(250) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `urlname` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  MODIFY `realname` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `concertinfo` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `type` enum('m','f','b','x') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'x',
  MODIFY `trivia` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `website` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `profile` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `artists_everyweek` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `artists_photos` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `filename` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `sha256` char(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `mime` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `main` enum('y','n') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'n',
  MODIFY `source` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `sourceurl` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `licence` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `licence_url` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `credit` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `artist_city_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `artist_concert_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `artist_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `band_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `cities` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `name` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `city_label_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `collection` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `feattypes` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `feattype` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `feature_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `hhb_comments` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci,
  MODIFY `com_content` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `com_author` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `com_author_ip` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `com_object_type` enum('a','n','p','l','s','u') CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci NOT NULL;

ALTER TABLE `hhb_users` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci,
  MODIFY `usr_email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci NOT NULL,
  MODIFY `usr_password` char(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci NOT NULL,
  MODIFY `usr_display_name` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci NULL DEFAULT NULL,
  MODIFY `usr_first_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci NULL DEFAULT NULL,
  MODIFY `usr_last_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci NULL DEFAULT NULL,
  MODIFY `usr_recovery_key` char(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci NULL DEFAULT '',
  MODIFY `usr_is_admin` enum('yes','no') CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci NOT NULL DEFAULT 'no';

ALTER TABLE `hhb_user_lyrics_edit` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `ule_action_type` enum('add','edit','delete') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `ule_lyrics` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `labels` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `name` varchar(250) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `urlname` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  MODIFY `website` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `email` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `addres` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `profile` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `logo` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL;

ALTER TABLE `music_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `news` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `news` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `title` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `glyph` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `graph` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL;

ALTER TABLE `news_album_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `news_artist_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `news_city_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `news_concert_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `news_label_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `ratings` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `ratings_avg` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `remix_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `name` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `scratch_lookup` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;

ALTER TABLE `searches` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `searchstring` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `songs` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `title` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `urlname` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  MODIFY `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `lyrics` text CHARACTER SET utf8mb3 COLLATE utf8mb3_polish_ci NOT NULL,
  MODIFY `youtube_url` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `song_samples` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `sample` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `submision_errors` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `addedby` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '0',
  MODIFY `site` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `sid` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `message` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `status` enum('w','r','a') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'w';

ALTER TABLE `submision_recommendations` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `email` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `signature` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  MODIFY `link` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL;

ALTER TABLE `users` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `login` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `pass` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `urlname` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  MODIFY `email` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `www` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `place` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `about` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `allow_wishlist` enum('y','n') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'y',
  MODIFY `allow_collection` enum('y','n') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'y';

ALTER TABLE `users_activations` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `activationstring` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL;

ALTER TABLE `users_admins` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  MODIFY `login` varchar(16) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `pass` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `name` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `email` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  MODIFY `news_priv` enum('y','n') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'n',
  MODIFY `conc_priv` enum('y','n') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'n',
  MODIFY `week_priv` enum('y','n') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'n',
  MODIFY `lala_priv` enum('y','n') CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'n';

ALTER TABLE `wishlist` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
