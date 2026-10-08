-- 0002 added-keeps-insert-time: down. Puts back the ON UPDATE current_timestamp() these columns
-- had in the baseline. Times overwritten while it is back are not restored by going up again.
ALTER TABLE `album_promomixes` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `album_reviews` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `artists_everyweek` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `artists_photos` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `collection` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `ratings` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `searches` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `song_samples` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `submision_errors` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `submision_recommendations` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `wishlist` MODIFY `added` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `hhb_user_lyrics_edit` MODIFY `ule_action_timestamp` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp();
