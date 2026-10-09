-- 0029 missing-images: down. The covers, the thumbnail's flag, the photo and the logo back as
-- they were, from the archive.
UPDATE `albums` a
  JOIN `migration_archive` m
    ON m.`version` = '0029' AND m.`table_name` = 'albums' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET a.`cover` = JSON_VALUE(m.`row_data`, '$.cover');

UPDATE `album_covers` c
  JOIN `migration_archive` m
    ON m.`version` = '0029' AND m.`table_name` = 'album_covers' AND m.`action` = 'changed'
   AND c.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET c.`needs_upgrade` = JSON_VALUE(m.`row_data`, '$.needs_upgrade');

INSERT INTO `artists_photos` (`id`, `artistid`, `filename`, `width`, `height`, `sha256`, `mime`, `description`, `main`, `source`,
                              `sourceurl`, `licence`, `licence_url`, `credit`, `modified`, `addedby`, `added`)
SELECT JSON_VALUE(`row_data`, '$.id'), JSON_VALUE(`row_data`, '$.artistid'), JSON_VALUE(`row_data`, '$.filename'),
       JSON_VALUE(`row_data`, '$.width'), JSON_VALUE(`row_data`, '$.height'), JSON_VALUE(`row_data`, '$.sha256'),
       JSON_VALUE(`row_data`, '$.mime'), JSON_VALUE(`row_data`, '$.description'), JSON_VALUE(`row_data`, '$.main'),
       JSON_VALUE(`row_data`, '$.source'), JSON_VALUE(`row_data`, '$.sourceurl'), JSON_VALUE(`row_data`, '$.licence'),
       JSON_VALUE(`row_data`, '$.licence_url'), JSON_VALUE(`row_data`, '$.credit'), JSON_VALUE(`row_data`, '$.modified'),
       JSON_VALUE(`row_data`, '$.addedby'), JSON_VALUE(`row_data`, '$.added')
  FROM `migration_archive`
 WHERE `version` = '0029' AND `table_name` = 'artists_photos' AND `action` = 'deleted';

UPDATE `labels` l
  JOIN `migration_archive` m
    ON m.`version` = '0029' AND m.`table_name` = 'labels' AND m.`action` = 'changed'
   AND l.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET l.`logo` = JSON_VALUE(m.`row_data`, '$.logo');

DELETE FROM `migration_archive` WHERE `version` = '0029';
