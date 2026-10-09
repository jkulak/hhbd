-- 0008 import-user: down. Removes the import's row. Rows the import added or changed keep 1100
-- in addedby and updatedby, which then names nobody, as 0 does.
DELETE FROM `users` WHERE `ID` = 1100 AND `login` = 'import';
