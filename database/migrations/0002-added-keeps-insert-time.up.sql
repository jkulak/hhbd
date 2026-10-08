-- 0002 added-keeps-insert-time: up. `added` records when a row was added, so it must not
-- change when the row does. In these eleven tables it was ON UPDATE current_timestamp(), which
-- overwrote it on every update; artists_photos already lost 58 of its 120 dates that way. The
-- default stays, so a row inserted without a time still gets the current one.
-- hhb_user_lyrics_edit's ule_action_timestamp is the time of the edit it logs and loses its
-- ON UPDATE too; the application sets it on insert. Each ALTER rewrites a MyISAM table:
-- seconds at this size.
ALTER TABLE `album_promomixes` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `album_reviews` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `artists_everyweek` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `artists_photos` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `collection` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `ratings` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `searches` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `song_samples` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `submision_errors` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `submision_recommendations` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `wishlist` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp();
ALTER TABLE `hhb_user_lyrics_edit` MODIFY `ule_action_timestamp` timestamp NULL DEFAULT NULL;
