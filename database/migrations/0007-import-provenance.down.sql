-- 0007 import-provenance: down. Drops both tables and everything recorded in them; provenance
-- first, since it references the runs.
DROP TABLE IF EXISTS `import_provenance`;
DROP TABLE IF EXISTS `import_runs`;
