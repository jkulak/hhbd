-- 0005 innodb: up. Every table on InnoDB; the baseline had 44 of 45 on MyISAM (#45).
-- MyISAM has no crash recovery, locks a whole table for each write, and gives the nightly
-- `mysqldump --single-transaction` no consistent snapshot. InnoDB fixes all three, and the
-- InnoDB settings in deploy/compose.ovh.yaml finally serve the tables they were tuned for.
--
-- Each ALTER copies one table into InnoDB and keeps it readable meanwhile; writes to it wait
-- until it is done. At about 23 MB of data, all 44 take seconds. An ALTER that fails leaves that
-- table on MyISAM, and the ones before it converted; converting a table twice is harmless, so
-- running this again after a fix finishes the job.
ALTER TABLE `album_artist_lookup` ENGINE=InnoDB;
ALTER TABLE `album_lookup` ENGINE=InnoDB;
ALTER TABLE `album_prices` ENGINE=InnoDB;
ALTER TABLE `album_promomixes` ENGINE=InnoDB;
ALTER TABLE `album_ratings` ENGINE=InnoDB;
ALTER TABLE `album_reviews` ENGINE=InnoDB;
ALTER TABLE `albums` ENGINE=InnoDB;
ALTER TABLE `altnames_lookup` ENGINE=InnoDB;
ALTER TABLE `artist_city_lookup` ENGINE=InnoDB;
ALTER TABLE `artist_concert_lookup` ENGINE=InnoDB;
ALTER TABLE `artist_lookup` ENGINE=InnoDB;
ALTER TABLE `artists` ENGINE=InnoDB;
ALTER TABLE `artists_everyweek` ENGINE=InnoDB;
ALTER TABLE `artists_photos` ENGINE=InnoDB;
ALTER TABLE `band_lookup` ENGINE=InnoDB;
ALTER TABLE `cities` ENGINE=InnoDB;
ALTER TABLE `city_artist_lookup` ENGINE=InnoDB;
ALTER TABLE `city_label_lookup` ENGINE=InnoDB;
ALTER TABLE `collection` ENGINE=InnoDB;
ALTER TABLE `feattypes` ENGINE=InnoDB;
ALTER TABLE `feature_lookup` ENGINE=InnoDB;
ALTER TABLE `hhb_user_lyrics_edit` ENGINE=InnoDB;
ALTER TABLE `hhb_users` ENGINE=InnoDB;
ALTER TABLE `labels` ENGINE=InnoDB;
ALTER TABLE `music_lookup` ENGINE=InnoDB;
ALTER TABLE `news` ENGINE=InnoDB;
ALTER TABLE `news_album_lookup` ENGINE=InnoDB;
ALTER TABLE `news_artist_lookup` ENGINE=InnoDB;
ALTER TABLE `news_city_lookup` ENGINE=InnoDB;
ALTER TABLE `news_concert_lookup` ENGINE=InnoDB;
ALTER TABLE `news_label_lookup` ENGINE=InnoDB;
ALTER TABLE `ratings` ENGINE=InnoDB;
ALTER TABLE `ratings_avg` ENGINE=InnoDB;
ALTER TABLE `remix_lookup` ENGINE=InnoDB;
ALTER TABLE `scratch_lookup` ENGINE=InnoDB;
ALTER TABLE `searches` ENGINE=InnoDB;
ALTER TABLE `song_samples` ENGINE=InnoDB;
ALTER TABLE `songs` ENGINE=InnoDB;
ALTER TABLE `submision_errors` ENGINE=InnoDB;
ALTER TABLE `submision_recommendations` ENGINE=InnoDB;
ALTER TABLE `users` ENGINE=InnoDB;
ALTER TABLE `users_activations` ENGINE=InnoDB;
ALTER TABLE `users_admins` ENGINE=InnoDB;
ALTER TABLE `wishlist` ENGINE=InnoDB;
