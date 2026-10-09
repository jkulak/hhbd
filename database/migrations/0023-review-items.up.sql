-- 0023 review-items: up. The doubts an import leaves for a person (#103): an artist added under
-- a qualifier because another has the name (#102), a stand-in cover, a release date or type the
-- sources disagree on, a work only one catalogue knew. An admin settles them on the page of the
-- row they are about, and the list of open ones is one page under the admin controller.
--
-- reason is a code (namesake, cover_placeholder, date_disputed, type_disputed, single_source);
-- detail holds what the doubt is, as JSON: the suggestions, the sources' values. An item is
-- open until resolved is set, with who settled it, how, and a note. undo_data keeps what a merge
-- moved and deleted, so a merge can be taken back by hand. Identifiers and JSON only, so the
-- table compares bytes, like the other import tables.
CREATE TABLE `review_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` enum('album','artist','label','song') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `reason` varchar(64) NOT NULL,
  `detail` json DEFAULT NULL,
  `run_id` int(11) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution` varchar(64) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `undo_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `i_review_items_entity` (`entity_type`, `entity_id`, `resolved`),
  KEY `i_review_items_open` (`resolved`, `reason`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- An artist merged into another: its old URL answers with a redirect to the one it became.
CREATE TABLE `artist_merges` (
  `id` int(11) NOT NULL,
  `into_id` int(11) NOT NULL,
  `review_item_id` int(11) DEFAULT NULL,
  `merged` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `i_artist_merges_into` (`into_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
