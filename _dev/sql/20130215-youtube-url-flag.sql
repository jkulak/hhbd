ALTER TABLE `kosiarkahhbdpl`.`songs` ADD COLUMN `youtube_url_flag` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'number of \'wrong video\' flags'  AFTER `youtube_url` ;