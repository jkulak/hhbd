-- 0032 news-images: up. The news images production's content volume lacks or the rows misname
-- (#133), found by `make ovh-check-images` once it checked news images too. Of the 191 news
-- items that name an image:
-- - 18 name a file that is in no copy, production's or the local one, among them 206's
--   'trÃ“', a name stored mangled: the row stops naming it, as for a news item without one;
-- - 5 name the start of a file that is there: the old upload wrote a Polish letter half
--   percent-encoded ('_jednoś%C4_z_wyrazami_wielkie.jpg') and the row kept the name only up to
--   the '%'. The row gets the file's whole name, which the page now encodes (#133).
--
-- Rows are named by id and value together, so a database that does not hold them, such as one
-- built from the fixtures, is not touched. Everything changed is archived (migration_archive,
-- 0009) and the down puts it back.
INSERT INTO `migration_archive` (`version`, `table_name`, `action`, `row_data`)
SELECT '0032', 'news', 'changed', JSON_OBJECT('id', `id`, 'graph', `graph`)
  FROM `news`
 WHERE (`id`, `graph`) IN (
  (206, 'www_hhbd_pl_2005-10-10-lona_w_trÃ“-w_niedziele__16_list.jpg'),
  (309, 'www_hhbd_pl_polski_hip_hop_2010_12_04_dzieje_sie_pracujemy_ostro_na.jpg'),
  (310, 'www_hhbd_pl_polski_hip_hop_2010_12_06_pracujemy_caly_czas_trwaja_pr.jpg'),
  (380, 'www_hhbd_pl_polski_hip_hop_2011_02_05_fokus_pr_zgadnie_z_zapowiedzi.jpg'),
  (390, 'www_hhbd_pl_polski_hip_hop_2011_02_10_zapracowan_w_ciągu_7_dni_og'),
  (425, 'www_hhbd_pl_polski_hip_hop_2011_03_05_dj_buhhpre_tylko_z_cgmpl_mo'),
  (495, '_jednoś'),
  (516, 'super_mc_apraszamy_na_trzeci'),
  (526, 'premiera_'),
  (598, 'nowy_album_głowa_i_wę'),
  (599, 'prosto_w_r_od_września_sok'),
  (1460, 'wyloguj-sie-do-zycia-a-robi-sie-to-tak-jak-wszyscy-wiemy-int.jpg'),
  (1504, 'sigma-nalegalinanielegal-preorder-promomix-okladka-i-trackli.jpg'),
  (1507, 'plyta-hiperchimera-donatana-i-cleo-juz-dostepna-w-salonach-e.jpg'),
  (1553, 'pluto-w-szeregach-pro-rec-rzeszowki-raper-znany-z-wystepow-p.jpg'),
  (1566, 'pro-rec-dla-wiezniow-rnblisko-dwiescie-plyt-wydanych-przez-s.jpg'),
  (1587, 'zapraszamy-na-pro-rec-hip-hop-festival-pro-rec-szykuje-mocne.jpg'),
  (1594, 'malach-tempo-mixtape-premiera-albumu-ktory-zostanie-wydany-n.jpg'),
  (1595, 'odsluch-plyty-rws-lokalny-patriota-zapraszamy-do-zapoznania-.jpg'),
  (1695, 'winylowy-raj-na-chmielnej-w-najblizsza-sobote-18-lipca-otwar.jpg'),
  (1701, 'leh-w-alkopoligamii-data-premiery-plyty-znana-pprnniektorzy-.jpg'),
  (1834, 'lasio-companija-w-szeregach-pro-rec-codziennie-zycie-jest-dl.jpg'),
  (1869, 'i-urodziny-domofonia-rec-pprni-urodziny-domofonia-recrnbrbrr.jpg')
 );

-- The files that are in no copy
UPDATE `news` SET `graph` = ''
 WHERE (`id`, `graph`) IN (
  (206, 'www_hhbd_pl_2005-10-10-lona_w_trÃ“-w_niedziele__16_list.jpg'),
  (309, 'www_hhbd_pl_polski_hip_hop_2010_12_04_dzieje_sie_pracujemy_ostro_na.jpg'),
  (310, 'www_hhbd_pl_polski_hip_hop_2010_12_06_pracujemy_caly_czas_trwaja_pr.jpg'),
  (380, 'www_hhbd_pl_polski_hip_hop_2011_02_05_fokus_pr_zgadnie_z_zapowiedzi.jpg'),
  (390, 'www_hhbd_pl_polski_hip_hop_2011_02_10_zapracowan_w_ciągu_7_dni_og'),
  (425, 'www_hhbd_pl_polski_hip_hop_2011_03_05_dj_buhhpre_tylko_z_cgmpl_mo'),
  (1460, 'wyloguj-sie-do-zycia-a-robi-sie-to-tak-jak-wszyscy-wiemy-int.jpg'),
  (1504, 'sigma-nalegalinanielegal-preorder-promomix-okladka-i-trackli.jpg'),
  (1507, 'plyta-hiperchimera-donatana-i-cleo-juz-dostepna-w-salonach-e.jpg'),
  (1553, 'pluto-w-szeregach-pro-rec-rzeszowki-raper-znany-z-wystepow-p.jpg'),
  (1566, 'pro-rec-dla-wiezniow-rnblisko-dwiescie-plyt-wydanych-przez-s.jpg'),
  (1587, 'zapraszamy-na-pro-rec-hip-hop-festival-pro-rec-szykuje-mocne.jpg'),
  (1594, 'malach-tempo-mixtape-premiera-albumu-ktory-zostanie-wydany-n.jpg'),
  (1595, 'odsluch-plyty-rws-lokalny-patriota-zapraszamy-do-zapoznania-.jpg'),
  (1695, 'winylowy-raj-na-chmielnej-w-najblizsza-sobote-18-lipca-otwar.jpg'),
  (1701, 'leh-w-alkopoligamii-data-premiery-plyty-znana-pprnniektorzy-.jpg'),
  (1834, 'lasio-companija-w-szeregach-pro-rec-codziennie-zycie-jest-dl.jpg'),
  (1869, 'i-urodziny-domofonia-rec-pprni-urodziny-domofonia-recrnbrbrr.jpg')
 );

-- The files that are there, by their whole names
UPDATE `news` SET `graph` = '_jednoś%C4_z_wyrazami_wielkie.jpg' WHERE `id` = 495 AND `graph` = '_jednoś';
UPDATE `news` SET `graph` = 'super_mc_apraszamy_na_trzeci%C4.jpg' WHERE `id` = 516 AND `graph` = 'super_mc_apraszamy_na_trzeci';
UPDATE `news` SET `graph` = 'premiera_%E2_dzisiaj_premierę_ma.jpg' WHERE `id` = 526 AND `graph` = 'premiera_';
UPDATE `news` SET `graph` = 'nowy_album_głowa_i_wę%C5zu_powr.jpg' WHERE `id` = 598 AND `graph` = 'nowy_album_głowa_i_wę';
UPDATE `news` SET `graph` = 'prosto_w_r_od_września_sok%C3lł.jpg' WHERE `id` = 599 AND `graph` = 'prosto_w_r_od_września_sok';
