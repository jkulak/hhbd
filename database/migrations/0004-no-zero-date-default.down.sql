-- 0004 no-zero-date-default: down. Back to the zero date the baseline had.
-- MODIFY, restating the baseline's column, for the reason 0003's down gives.
ALTER TABLE `album_prices` MODIFY `added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00';
