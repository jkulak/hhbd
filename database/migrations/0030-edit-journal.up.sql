-- 0030 edit-journal: up. A record of every edit an admin makes to the catalogue outside the
-- importer and the migrations, and the way back (#115). Until now such an edit was SQL typed
-- over ssh, with no record of who changed what, when or why.
--
-- edit_operations has one row per call: what it did and to which rows, who (an hhbd admin's
-- usr_id), why, through which path (the command line or the review panel), and, once undone,
-- the operation that undid it. edit_journal has one row per row changed: its table, whether it
-- was inserted, changed or deleted, and the row before and after as JSON; undoing an operation
-- reads its journal backwards. Identifiers and JSON, so both compare bytes.
CREATE TABLE `edit_operations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operation` varchar(32) NOT NULL,
  `args` json NOT NULL,
  `user_id` int(11) NOT NULL,
  `why` varchar(500) NOT NULL,
  `path` enum('cli','panel') NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `undone_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE `edit_journal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operation_id` int(11) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `action` enum('inserted','changed','deleted') NOT NULL,
  `row_before` json DEFAULT NULL,
  `row_after` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `i_edit_journal_operation` (`operation_id`),
  CONSTRAINT `fk_edit_journal_operation` FOREIGN KEY (`operation_id`) REFERENCES `edit_operations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- An album merged into another: its old URL answers with a redirect to the one kept, as
-- artist_merges does for artists (0023).
CREATE TABLE `album_merges` (
  `id` int(11) NOT NULL,
  `into_id` int(11) NOT NULL,
  `operation_id` int(11) DEFAULT NULL,
  `merged` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `i_album_merges_into` (`into_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
