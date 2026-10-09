-- 0008 import-user: up. The import's own row in users, the table addedby and updatedby point at
-- (#48), so a row the import added or changed says so in those two columns without a look at
-- import_provenance (#63). The id is fixed at 1100, the same in every database, so the README
-- and the importer's configuration can name it; production's old accounts end at 1051.
-- No password: nothing logs in as it, and the backoffice that used these accounts is archived
-- for good anyway.
INSERT INTO `users` (`ID`, `login`, `pass`, `name`, `urlname`, `added`, `status`)
VALUES (1100, 'import', NULL, 'Import', 'import', current_timestamp(), 0);
