-- 0007 import-provenance: up. What each import run did, and where every field it set came from
-- (#52). Sources differ in licence (Discogs data CC0, Commons photos per file, covers tolerated
-- with a takedown path), so "where from" decides what may be shown and how; a licence question
-- or a takedown gets answered per row and per file, and a later batch can tell the fields it set
-- from those a person edited since.
--
-- import_runs: one row per batch file the importer reads, a dry run included. The counts are
-- the report's totals; report is the whole report, checked as JSON by the database.
--
-- import_provenance: one row per field, per source that supplied it; a field confirmed by two
-- sources has two rows. entity_type/entity_id point at albums, artists, labels, songs or images
-- with no foreign key, as in external_ids; run_id is the run that last wrote the row. Both
-- tables compare bytes (utf8mb4_bin), like external_ids: field names, sources and references
-- are identifiers, not text to sort. The source vocabulary is Model_Provenance_Api's and
-- database/README.md's.
CREATE TABLE `import_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch` varchar(190) NOT NULL,
  `batch_sha256` char(64) NOT NULL,
  `mode` enum('dry-run','apply') NOT NULL,
  `started` datetime NOT NULL DEFAULT current_timestamp(),
  `finished` datetime DEFAULT NULL,
  `created_count` int(11) NOT NULL DEFAULT 0,
  `updated_count` int(11) NOT NULL DEFAULT 0,
  `unchanged_count` int(11) NOT NULL DEFAULT 0,
  `skipped_count` int(11) NOT NULL DEFAULT 0,
  `refused_count` int(11) NOT NULL DEFAULT 0,
  `report` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE `import_provenance` (
  `entity_type` enum('album','artist','label','song','image') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `field` varchar(64) NOT NULL,
  `source` varchar(32) NOT NULL,
  `source_ref` varchar(500) NOT NULL,
  `licence` varchar(64) DEFAULT NULL,
  `fetched` datetime NOT NULL,
  `run_id` int(11) NOT NULL,
  `added` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`entity_type`, `entity_id`, `field`, `source`),
  KEY `i_import_provenance_run` (`run_id`),
  CONSTRAINT `fk_import_provenance_run` FOREIGN KEY (`run_id`) REFERENCES `import_runs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
