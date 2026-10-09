-- 0029 missing-images: up. The 89 image files the catalogue names that production's content
-- volume has lacked since it was restored from a 2014 snapshot (#47): 87 album covers, one
-- artist photo, one label logo. They are in no backup, archive or old disk (decided
-- 2026-10-09), so the rows stop naming them:
-- - each album's cover becomes '', as for an album that never had one, and the pages show
--   the placeholder;
-- - the photo's row goes;
-- - the logo becomes ''.
-- The import then fills them: an album with no album_covers row takes the cover a batch brings
-- (#56, #60).
--
-- The one of those albums that has a row, "Prawda Naga" (576), has only the 75 px thumbnail
-- that survived. That row is marked needs_upgrade, so it shows in the lists until a larger
-- cover comes, and that cover replaces it (#103).
--
-- Rows are named by id and file together, so a database that does not hold them, such as one
-- built from the fixtures, is not touched. Everything changed is archived (migration_archive,
-- 0009) and the down puts it back.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0029', 'albums', 'changed', JSON_OBJECT('id', `id`, 'cover', `cover`)
  FROM `albums`
 WHERE (`id`, `cover`) IN (
  (576, 'wally-prawda-naga-hhbdpl.jpg'),
  (753, 'medium-graal-hhbdpl.jpg'),
  (767, 'dod-bękarty-rap-gry-hhbdpl.jpg'),
  (777, 'b&b-highway-2-hell-hhbdpl.jpg'),
  (834, 'hukos-wielkie-wojny-małych-ludzi-hhbdpl.jpg'),
  (845, 'praktis-syzyf-hhbdpl.jpg'),
  (847, 'borixon-new-bad-life-hhbdpl.jpg'),
  (858, 'pih-kino-nocne-hhbdpl.jpg'),
  (863, 'diox-7-minut-po-śmierci-hhbdpl.jpg'),
  (875, 'onar-autodestrukcja-hhbdpl.jpg'),
  (890, 'kali-sentymentalnie-hhbdpl.jpg'),
  (891, 'cywil-lęk-wysokości-hhbdpl.jpg'),
  (893, 'kafar-panaceum-hhbdpl.jpg'),
  (894, 'ry23-&-rudi-western-4-hhbdpl.jpg'),
  (895, 'flint-zła-sława-hhbdpl.jpg'),
  (896, '2sty-stej-flaj-hhbdpl.jpg'),
  (897, 'sarius-daleko-jeszcze--hhbdpl.jpg'),
  (898, 'tau-remedium-hhbdpl.jpg'),
  (899, 'młody-m-wiecznie-młody-m-hhbdpl.jpg'),
  (900, 'rozbójnik-alibaba-bal-maturalny-hhbdpl.jpg'),
  (901, 'Łysonżi-browka-szpinak-hhbdpl.jpg'),
  (902, 'nizioł-pretekst-hhbdpl.jpg'),
  (903, 'zbuku-Życie-szalonym-Życiem-hhbdpl.jpg'),
  (904, 'fabster-kontrasty-hhbdpl.jpg'),
  (905, 'hv-noon-hv-noon-hhbdpl.jpg'),
  (906, 'chada-efekt-porozumienia-hhbdpl.jpg'),
  (907, 'buczer-emocje-hhbdpl.jpg'),
  (908, 'bonson-&-matek-mvp-hhbdpl.jpg'),
  (909, 'blasq-bez-urazy-hhbdpl.jpg'),
  (910, 'dwa-sławy-ludzie-sztosy-hhbdpl.jpg'),
  (911, 'małolat-więcej-hhbdpl.jpg'),
  (912, 'wini-100--głodny-duch-hhbdpl.jpg'),
  (913, 'o-s-t-r--podróż-zwana-życiem-hhbdpl.jpg'),
  (914, 'quebonafide-ezoteryka-hhbdpl.jpg'),
  (915, 'hades-czasoprzestrzeń-hhbdpl.jpg'),
  (916, 'kękę-nowe-rzeczy-hhbdpl.jpg'),
  (917, 'kaen-tylko-śmierć-może-mnie-powstrzymać-hhbdpl.jpg'),
  (918, 'dudek-rpk-polski-rap-hhbdpl.jpg'),
  (919, 'parzel-oddech-za-oddech-hhbdpl.jpg'),
  (920, 'grubson-holizm-hhbdpl.jpg'),
  (921, 'małach-tempo-mixtape-hhbdpl.jpg'),
  (922, 'pluto-projekt-wilcze-zmysły-hhbdpl.jpg'),
  (924, 'b-r-o--next-level-2-hhbdpl.jpg'),
  (925, 'bonus-rpk-losu-kowal-hhbdpl.jpg'),
  (926, 'tede-vanillahajs-hhbdpl.jpg'),
  (927, 'sulin-taksydermia-hhbdpl.jpg'),
  (928, 'rasmentalism-wyszli-coś-zjeść-hhbdpl.jpg'),
  (929, 'mielzky---patr00-miejski-patrol-hhbdpl.jpg'),
  (930, 'gang-albanii-królowie-życia-hhbdpl.jpg'),
  (931, 'chada--bezczel--zbuku-kontrabanda---brat-bratu-bratem-hhbdpl.jpg'),
  (932, 'vnm-klaud-n9ajn-hhbdpl.jpg'),
  (933, 'trzeci-wymiar-odmienny-stan-świadomości-hhbdpl.jpg'),
  (934, 'w-e-n-a--monochromy-hhbdpl.jpg'),
  (935, 'steel-banging-dwie-strony-Świata-hhbdpl.jpg'),
  (936, 'bilon-3-x-nie-hhbdpl.jpg'),
  (937, 'voskovy-second-hand-hhbdpl.jpg'),
  (938, 'proceente-&-bleiz-aloha-grill-hhbdpl.jpg'),
  (940, 'kleszcz-&-dino-horrym-jestem-hhbdpl.jpg'),
  (941, 'leh-podwórka-pytają-kiedy-płyta-hhbdpl.jpg'),
  (942, 'tetris-definitywnie-hhbdpl.jpg'),
  (944, 'sobota-sobota-hhbdpl.jpg'),
  (945, 'kajman-0-1-hhbdpl.jpg'),
  (946, 'buka-&-rahim-optymistycznie-hhbdpl.jpg'),
  (947, 'jwp-sequel-hhbdpl.jpg'),
  (948, 'gural-magnum-ignotum-preludium-hhbdpl.jpg'),
  (949, 'pono-bunt-hhbdpl.jpg'),
  (950, 'zeus-jest-super-hhbdpl.jpg'),
  (951, 'lukasyno-antybanger-hhbdpl.jpg'),
  (952, 'skorup-&-jazbrothers-ludzie-chmur-hhbdpl.jpg'),
  (954, 'v-a-prosto-mixtape-cztery-hhbdpl.jpg'),
  (955, 'rest-wiara--nadzieja--miłość-hhbdpl.jpg'),
  (956, 'białas-rehab-hhbdpl.jpg'),
  (957, 'bezczel-teraz-albo-nigdy-hhbdpl.jpg'),
  (958, 'tau-restaurator-hhbdpl.jpg'),
  (959, 'osiedlowe-linie-lotnicze-lot-pierwszy-hhbdpl.jpg'),
  (960, 'popek-x-matheo-król-albanii-hhbdpl.jpg'),
  (961, 'pawbeats-pawbeats-orchestra-hhbdpl.jpg'),
  (962, 'małpa-mówi-hhbdpl.jpg'),
  (963, 'kaliber-44-ułamek-tarcia-hhbdpl.jpg'),
  (964, 'paluch-10-29-hhbdpl.jpg'),
  (965, 'soker-tyno-ponad-miarę-hhbdpl.jpg'),
  (967, 'Łona-nawiasem-mówiąc-hhbdpl.jpg'),
  (968, 'pele-bumelant-hhbdpl.jpg'),
  (969, 'szad-człowiek-duch-hhbdpl.jpg'),
  (970, 'fu-definicja-istnienia-hhbdpl.jpg'),
  (971, 'egon-bez-odwrotu-hhbdpl.jpg'),
  (972, 'tede-keptn-hhbdpl.jpg')
 );

UPDATE `albums` a
  JOIN `migration_archive` m
    ON m.`version` = '0029' AND m.`table_name` = 'albums' AND m.`action` = 'changed'
   AND a.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET a.`cover` = '';

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0029', 'album_covers', 'changed', JSON_OBJECT('id', c.`id`, 'needs_upgrade', c.`needs_upgrade`)
  FROM `album_covers` c
  JOIN `migration_archive` m
    ON m.`version` = '0029' AND m.`table_name` = 'albums' AND m.`action` = 'changed'
   AND c.`albumid` = JSON_VALUE(m.`row_data`, '$.id')
 WHERE c.`needs_upgrade` = 0;

UPDATE `album_covers` c
  JOIN `migration_archive` m
    ON m.`version` = '0029' AND m.`table_name` = 'album_covers' AND m.`action` = 'changed'
   AND c.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET c.`needs_upgrade` = 1;

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0029', 'artists_photos', 'deleted', JSON_OBJECT(
         'id', `id`, 'artistid', `artistid`, 'filename', `filename`, 'width', `width`, 'height', `height`,
         'sha256', `sha256`, 'mime', `mime`, 'description', `description`, 'main', `main`, 'source', `source`,
         'sourceurl', `sourceurl`, 'licence', `licence`, 'licence_url', `licence_url`, 'credit', `credit`,
         'modified', `modified`, 'addedby', `addedby`, 'added', `added`)
  FROM `artists_photos`
 WHERE `id` = 120 AND `filename` = 'Enemis-1-hhbdpl.jpg';

DELETE p FROM `artists_photos` p
  JOIN `migration_archive` m
    ON m.`version` = '0029' AND m.`table_name` = 'artists_photos' AND m.`action` = 'deleted'
   AND p.`id` = JSON_VALUE(m.`row_data`, '$.id');

INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0029', 'labels', 'changed', JSON_OBJECT('id', `id`, 'logo', `logo`)
  FROM `labels`
 WHERE `id` = 57 AND `logo` = 'www_hhbd_pl_step_records-logo.jpg';

UPDATE `labels` l
  JOIN `migration_archive` m
    ON m.`version` = '0029' AND m.`table_name` = 'labels' AND m.`action` = 'changed'
   AND l.`id` = JSON_VALUE(m.`row_data`, '$.id')
   SET l.`logo` = '';
