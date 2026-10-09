-- 0006 external-ids: up. Ids a catalogue row has in other databases (#51), so an import can find
-- the row it created last time instead of adding it again, and a correction can find the row it
-- corrects. A row may carry many ids, because sources split, merge and add them over time; an id
-- belongs to at most one row, which the primary key enforces.
--
-- entity_type/entity_id point at albums, artists, labels or songs; there is no foreign key,
-- since one column cannot reference four tables. The vocabulary of source and kind, and how a
-- value is normalised before it is stored (barcodes as GTIN-14), is Model_ExternalId_Api's and
-- database/README.md's. The columns compare bytes (utf8mb4_bin): an id matches exactly or not
-- at all, whatever collation the catalogue tables move to (#71).
CREATE TABLE `external_ids` (
  `entity_type` enum('album','artist','label','song') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `source` varchar(32) NOT NULL,
  `kind` varchar(32) NOT NULL,
  `value` varchar(190) NOT NULL,
  `added` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`source`, `kind`, `value`),
  KEY `i_external_ids_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
