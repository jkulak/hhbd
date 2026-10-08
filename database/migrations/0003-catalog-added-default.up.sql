-- 0003 catalog-added-default: up. A row added to the catalog gets the time it was added even
-- when whatever inserts it does not say. The archived backoffice set added itself; nothing does
-- now. updated gets no automatic value: the application bumps viewed on these rows on every
-- page view, and an ON UPDATE would turn "last edited" into "last viewed". Only the default
-- changes, so no table is rewritten.
ALTER TABLE `albums` ALTER COLUMN `added` SET DEFAULT current_timestamp();
ALTER TABLE `artists` ALTER COLUMN `added` SET DEFAULT current_timestamp();
ALTER TABLE `songs` ALTER COLUMN `added` SET DEFAULT current_timestamp();
ALTER TABLE `labels` ALTER COLUMN `added` SET DEFAULT current_timestamp();
ALTER TABLE `cities` ALTER COLUMN `added` SET DEFAULT current_timestamp();
ALTER TABLE `news` ALTER COLUMN `added` SET DEFAULT current_timestamp();
