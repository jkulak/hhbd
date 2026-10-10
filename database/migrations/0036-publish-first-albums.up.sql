-- 0036 publish-first-albums: up. The site's first 77 albums, added from June to 7 November
-- 2004, have status 0 only because the site set none in its first months; every album added
-- since has 999. No page asked, so they have always been shown. Now that an unpublished album
-- is hidden from visitors (#168), they are published, as they always looked. Their ids and
-- status go into migration_archive (0009) for the down.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0036', 'albums', 'changed', JSON_OBJECT('id', `id`, 'status', `status`)
  FROM `albums`
 WHERE `status` <> 999 AND `added` < '2004-11-09';

UPDATE `albums` SET `status` = 999 WHERE `status` <> 999 AND `added` < '2004-11-09';
