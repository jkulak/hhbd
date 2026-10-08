-- 0004 no-zero-date-default: up. album_prices.added defaulted to '0000-00-00 00:00:00', a date
-- strict SQL modes reject; no row holds one (0 of 928 on 2026-10-09), so only the default goes.
ALTER TABLE `album_prices` ALTER COLUMN `added` SET DEFAULT current_timestamp();
