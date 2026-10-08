-- 0004 no-zero-date-default: down. Back to the zero date the baseline had.
ALTER TABLE `album_prices` ALTER COLUMN `added` SET DEFAULT '0000-00-00 00:00:00';
