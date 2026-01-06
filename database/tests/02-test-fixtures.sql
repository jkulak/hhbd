-- ============================================
-- HHBD Test Fixtures for Smoke Tests
-- ============================================
-- This file contains deterministic test data with specific IDs
-- required by smoke tests. Run after 01-schema.sql.
--
-- Usage:
--   mysql -u hhbd -p hhbd < database/tests/01-schema.sql
--   mysql -u hhbd -p hhbd < database/tests/02-test-fixtures.sql
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================
-- 1. LABELS (15 labels for Top10 + specific IDs)
-- ============================================
INSERT INTO `labels` (`id`, `name`, `website`, `logo`, `status`, `viewed`) VALUES
(1, 'Asfalt', 'http://asfalt.pl', 'test-label-001.jpg', 999, 5000),
(2, 'Prosto', 'http://prosto.pl', 'test-label-002.jpg', 999, 4500),
(3, 'Step Records', 'http://steprecords.pl', 'test-label-003.jpg', 999, 4000),
(4, 'My Music', 'http://mymusic.pl', 'test-label-004.jpg', 999, 3500),
(5, 'T1-Teraz', 'http://t1teraz.pl', 'test-label-005.jpg', 999, 3000),
(6, 'EMI Music Poland', 'http://emimusic.pl', 'test-label-006.jpg', 999, 2500),
(7, 'Universal Music Polska', 'http://universalmusic.pl', 'test-label-007.jpg', 999, 2000),
(8, 'Fonografika', 'http://fonografika.pl', 'test-label-008.jpg', 999, 1800),
(9, 'RRX', 'http://rrx.pl', 'test-label-009.jpg', 999, 1600),
(10, 'Hemp Records', 'http://hemprecords.pl', 'test-label-010.jpg', 999, 1400),
(11, 'QL Records', 'http://qlrecords.pl', 'test-label-011.jpg', 999, 1200),
(12, 'Wielkie Joł', 'http://wielkiejol.pl', 'test-label-012.jpg', 999, 1000),
(13, 'Karrot Kommando', 'http://karrotkommando.pl', 'test-label-013.jpg', 999, 800),
(14, 'Def Jam Polska', 'http://defjam.pl', 'test-label-014.jpg', 999, 600),
(58, 'Alkopoligamia', 'http://alkopoligamia.pl', 'test-label-015.jpg', 999, 3200);

-- ============================================
-- 2. ARTISTS (50+ artists for Top10 + specific IDs)
-- Including Polish characters: ą, ę, ć, ź, ż, ó, ł, ś, ń
-- ============================================
INSERT INTO `artists` (`id`, `name`, `realname`, `type`, `trivia`, `website`, `status`, `viewed`, `profile`) VALUES
-- Required specific artists
(1, 'Pezet', 'Paweł Kapliński', 'm', '', '', 999, 15000, 'Jeden z najważniejszych polskich raperów.'),
(2, 'Eldo', 'Leszek Kaźmierczak', 'm', '', '', 999, 12000, 'Członek legendarnego Grammatik.'),
(3, 'Stasiak', 'Michał Stasiak', 'm', '', '', 999, 8000, 'Raper z Poznania.'),
(4, 'Mefistotedes', NULL, 'm', '', '', 999, 7000, 'Alter ego Tedego.'),
(5, 'MercTedes', NULL, 'm', '', '', 999, 6500, 'Inny projekt Tedego.'),
(6, 'Dj Technik', 'Tomasz Kowalski', 'm', '', '', 999, 6000, 'DJ i producent.'),
(7, 'Beatmo', 'Adam Nowak', 'm', '', '', 999, 5500, 'Producent muzyczny.'),
(8, 'Wdowa', NULL, 'b', '', '', 999, 10000, 'Zespół hip-hopowy.'),
(35, 'Mes', 'Piotr  Szmidt', 'm', '', '', 999, 9000, 'Raper z Krakowa.'),
-- Additional artists for Top10 (with Polish characters)
(9, 'Tede', 'Jacek Graniecki', 'm', '', '', 999, 14000, 'Kontrowersyjny raper.'),
(10, 'Sokół', 'Wojciech Sosnowski', 'm', '', '', 999, 13000, 'Legenda polskiego hip-hopu.'),
(11, 'Paluch', 'Łukasz Paluszak', 'm', '', '', 999, 11000, 'Raper z Poznania.'),
(12, 'Peja', 'Ryszard Andrzejewski', 'm', '', '', 999, 10500, 'Slums Attack.'),
(13, 'Onar', 'Patryk Skoczyński', 'm', '', '', 999, 9500, 'Raper z Warszawy.'),
(14, 'O.S.T.R.', 'Adam Ostrowski', 'm', '', '', 999, 9200, 'Wielokrotnie nagradzany artysta.'),
(15, 'Kali', 'Marcin Gutkowski', 'm', '', '', 999, 8800, 'Raper i muzyk.'),
(16, 'Włodi', 'Włodzimierz Kowalczyk', 'm', '', '', 999, 8500, 'Członek Molesty.'),
(17, 'Vienio', 'Sławomir Więcek', 'm', '', '', 999, 8200, 'Legendarny MC.'),
(18, 'Abradab', 'Marcin Marten', 'm', '', '', 999, 8000, 'Członek Kaliber 44.'),
(19, 'Łona', 'Adam Zieliński', 'm', '', '', 999, 7800, 'Raper i poeta.'),
(20, 'Zeus', 'Bartosz Zieliński', 'm', '', '', 999, 7500, 'Połowa duetu Łona i Webber.'),
(21, 'Donguralesko', 'Maciej Staruszkiewicz', 'm', '', '', 999, 7200, 'Koneser polskiego hip-hopu.'),
(22, 'Grammatik', NULL, 'b', '', '', 999, 7000, 'Legendarny zespół z Warszawy.'),
(23, 'Paktofonika', NULL, 'b', '', '', 999, 6800, 'Kultowy zespół z Krakowa.'),
(24, 'Molesta Ewenement', NULL, 'b', '', '', 999, 6600, 'Zespół z Krakowa.'),
(25, 'WWO', NULL, 'b', '', '', 999, 6400, 'Warszawski Wilk Obcy.'),
(26, 'Hemp Gru', NULL, 'b', '', '', 999, 6200, 'Zespół Hemp Gru.'),
(27, 'Slums Attack', NULL, 'b', '', '', 999, 6000, 'Legendarna formacja.'),
(28, 'Kaliber 44', NULL, 'b', '', '', 999, 5800, 'Pionierzy polskiego hip-hopu.'),
(29, 'Zipera', 'Radek Zieliński', 'm', '', '', 999, 5600, 'Raper z Katowic.'),
(30, 'Bisz', 'Bartosz Zdziarski', 'm', '', '', 999, 5400, 'Niezależny artysta.'),
(31, 'Rahim', 'Rahim Błąd', 'm', '', '', 999, 5200, 'Raper i producent.'),
(32, 'Grubson', 'Tomasz Gruszka', 'm', '', '', 999, 5000, 'Popularny polski raper.'),
(33, 'Proceente', 'Jarosław Jaruszewski', 'm', '', '', 999, 4800, 'Raper i poeta.'),
(34, 'Pih', 'Piotr Siciński', 'm', '', '', 999, 4600, 'Raper z Radomia.'),
(36, 'DJ 600V', 'Piotr Witkowski', 'm', '', '', 999, 4400, 'Legendarny DJ.'),
(37, 'Noon', 'Jakub Szczęsny', 'm', '', '', 999, 4200, 'Producent muzyczny.'),
(38, 'White House', 'Jakub Białek', 'm', '', '', 999, 4000, 'Producent.'),
(39, 'Emade', 'Marek Dziedzic', 'm', '', '', 999, 3800, 'Producent i DJ.'),
(40, 'Webber', 'Andrzej Weber', 'm', '', '', 999, 3600, 'Producent muzyczny.'),
(41, 'Ero JWP', 'Błażej Sieńczak', 'm', '', '', 999, 3400, 'Raper z Olsztyna.'),
(42, 'Bęsiu', 'Sebastian Bęsiu', 'm', '', '', 999, 3200, 'Raper i producent.'),
(43, 'VNM', 'Janusz Walczuk', 'm', '', '', 999, 3000, 'Raper.'),
(44, 'Słoń', 'Michał Słoń', 'm', '', '', 999, 2800, 'Raper z WWA.'),
(45, 'Kaz Bałagane', 'Karol Bałaga', 'm', '', '', 999, 2600, 'Młody raper.'),
(46, 'Miuosh', 'Miłosz Borycki', 'm', '', '', 999, 2400, 'Raper z Katowic.'),
(47, 'Ten Typ Mes', 'Łukasz Bulat-Mironowicz', 'm', '', '', 999, 2200, 'Członek Diox.'),
(48, 'Quebonafide', 'Kuba Grabowski', 'm', '', '', 999, 2000, 'Młoda gwiazda.'),
(49, 'Taco Hemingway', 'Filip Szcześniak', 'm', '', '', 999, 1800, 'Raper nowej fali.'),
(50, 'Żabson', 'Sebastian Żabski', 'm', '', '', 999, 1600, 'Raper.'),
(51, 'Borixon', 'Borys Kowalski', 'm', '', '', 999, 1400, 'Raper i wokalista.');

-- ============================================
-- 3. ALBUMS (50 albums for Top10 thumbnails + specific IDs)
-- ============================================
INSERT INTO `albums` (`id`, `title`, `labelid`, `year`, `legal`, `cover`, `artistabout`, `status`, `viewed`) VALUES
-- Required specific albums
(535, 'Superextra', 58, '2010-06-15', 'y', 'test-cover-001.jpg', '', 999, 8000),
-- Album with "Jestem Hip Hopem" title
(1, 'Jestem Hip Hopem', 2, '2005-03-01', 'y', 'test-cover-002.jpg', '', 999, 12000),
-- Additional albums for Top10 (using Polish characters in titles)
(2, 'Muzyka Poważna', 1, '2006-05-20', 'y', 'test-cover-003.jpg', '', 999, 11000),
(3, 'Trzecia Część Tryptyku', 2, '2007-09-15', 'y', 'test-cover-004.jpg', '', 999, 10500),
(4, 'Światła Miasta', 3, '2008-11-10', 'y', 'test-cover-005.jpg', '', 999, 10000),
(5, 'Kinematografia', 4, '2009-02-28', 'y', 'test-cover-006.jpg', '', 999, 9500),
(6, 'Podróż Zwana Życiem', 5, '2010-04-15', 'y', 'test-cover-007.jpg', '', 999, 9000),
(7, 'Nowy Świat', 6, '2011-06-01', 'y', 'test-cover-008.jpg', '', 999, 8500),
(8, 'Muzyka Klasyczna', 7, '2012-08-20', 'y', 'test-cover-009.jpg', '', 999, 8200),
(9, 'Prosto z Ulicy', 8, '2013-10-05', 'y', 'test-cover-010.jpg', '', 999, 7900),
(10, 'Miejska Dżungla', 9, '2014-12-12', 'y', 'test-cover-011.jpg', '', 999, 7600),
(11, 'Życie Po Życiu', 10, '2015-01-20', 'y', 'test-cover-012.jpg', '', 999, 7300),
(12, 'Koniec Świata', 11, '2016-03-15', 'y', 'test-cover-013.jpg', '', 999, 7000),
(13, 'Ostatni Taniec', 12, '2017-05-10', 'y', 'test-cover-014.jpg', '', 999, 6700),
(14, 'Mój Świat', 13, '2018-07-25', 'y', 'test-cover-015.jpg', '', 999, 6400),
(15, 'Dzień Dobry', 14, '2019-09-01', 'y', 'test-cover-016.jpg', '', 999, 6100),
(16, 'Nocne Życie', 1, '2020-11-11', 'y', 'test-cover-017.jpg', '', 999, 5800),
(17, 'Rewolucja', 2, '2021-01-05', 'y', 'test-cover-018.jpg', '', 999, 5500),
(18, 'Droga do Sukcesu', 3, '2022-03-20', 'y', 'test-cover-019.jpg', '', 999, 5200),
(19, 'Młody Wilk', 4, '2023-05-15', 'y', 'test-cover-020.jpg', '', 999, 4900),
(20, 'Stare Dobre Czasy', 5, '2024-07-10', 'y', 'test-cover-021.jpg', '', 999, 4600),
(21, 'Nowa Era', 6, '2005-09-05', 'y', 'test-cover-022.jpg', '', 999, 4300),
(22, 'Piąty Element', 7, '2006-11-20', 'y', 'test-cover-023.jpg', '', 999, 4000),
(23, 'Szósty Zmysł', 8, '2007-01-15', 'y', 'test-cover-024.jpg', '', 999, 3700),
(24, 'Siódme Niebo', 9, '2008-03-10', 'y', 'test-cover-025.jpg', '', 999, 3400),
(25, 'Ósmy Cud', 10, '2009-05-05', 'y', 'test-cover-026.jpg', '', 999, 3100),
(26, 'Dziewiąta Fala', 11, '2010-07-01', 'y', 'test-cover-027.jpg', '', 999, 2800),
(27, 'Dziesiątka', 12, '2011-09-20', 'y', 'test-cover-028.jpg', '', 999, 2500),
(28, 'Jedenaście', 13, '2012-11-15', 'y', 'test-cover-029.jpg', '', 999, 2200),
(29, 'Dwanaście', 14, '2013-01-10', 'y', 'test-cover-030.jpg', '', 999, 1900),
(30, 'Trzynaście', 1, '2014-03-05', 'y', 'test-cover-031.jpg', '', 999, 1600),
(31, 'Czternaście', 2, '2015-05-01', 'y', 'test-cover-032.jpg', '', 999, 1300),
(32, 'Piętnaście', 3, '2016-07-20', 'y', 'test-cover-033.jpg', '', 999, 1000),
(33, 'Szesnaście', 4, '2017-09-15', 'y', 'test-cover-034.jpg', '', 999, 900),
(34, 'Siedemnaście', 5, '2018-11-10', 'y', 'test-cover-035.jpg', '', 999, 800),
(36, 'Osiemnaście', 6, '2019-01-05', 'y', 'test-cover-036.jpg', '', 999, 700),
(37, 'Dziewiętnaście', 7, '2020-03-01', 'y', 'test-cover-037.jpg', '', 999, 600),
(38, 'Dwadzieścia', 8, '2021-05-20', 'y', 'test-cover-038.jpg', '', 999, 500),
(39, 'Słoneczne Dni', 9, '2022-07-15', 'y', 'test-cover-039.jpg', '', 999, 400),
(40, 'Deszczowe Noce', 10, '2023-09-10', 'y', 'test-cover-040.jpg', '', 999, 300),
(41, 'Zimowa Opowieść', 11, '2024-11-05', 'y', 'test-cover-041.jpg', '', 999, 200),
(42, 'Wiosenna Burza', 12, '2005-01-01', 'y', 'test-cover-042.jpg', '', 999, 150),
(43, 'Letni Wieczór', 13, '2006-03-20', 'y', 'test-cover-043.jpg', '', 999, 140),
(44, 'Jesienny Liść', 14, '2007-05-15', 'y', 'test-cover-044.jpg', '', 999, 130),
(45, 'Górskie Echo', 1, '2008-07-10', 'y', 'test-cover-045.jpg', '', 999, 120),
(46, 'Morska Bryza', 2, '2009-09-05', 'y', 'test-cover-046.jpg', '', 999, 110),
(47, 'Leśna Ścieżka', 3, '2010-11-01', 'y', 'test-cover-047.jpg', '', 999, 100),
(48, 'Miejski Rytm', 4, '2011-01-20', 'y', 'test-cover-048.jpg', '', 999, 90),
(49, 'Wiejska Cisza', 5, '2012-03-15', 'y', 'test-cover-049.jpg', '', 999, 80),
(50, 'Podmiejski Gwar', 6, '2013-05-10', 'y', 'test-cover-050.jpg', '', 999, 70);

-- ============================================
-- 4. ALBUM_ARTIST_LOOKUP (Links albums to artists)
-- ============================================
INSERT INTO `album_artist_lookup` (`albumid`, `artistid`, `status`) VALUES
-- Wdowa - Superextra
(535, 8, 999),
-- Jestem Hip Hopem by various
(1, 1, 999),
(1, 2, 999),
-- Additional albums mapped to artists
(2, 1, 999),
(3, 2, 999),
(4, 3, 999),
(5, 9, 999),
(6, 10, 999),
(7, 11, 999),
(8, 12, 999),
(9, 13, 999),
(10, 14, 999),
(11, 15, 999),
(12, 16, 999),
(13, 17, 999),
(14, 18, 999),
(15, 19, 999),
(16, 20, 999),
(17, 21, 999),
(18, 22, 999),
(19, 23, 999),
(20, 24, 999),
(21, 25, 999),
(22, 26, 999),
(23, 27, 999),
(24, 28, 999),
(25, 29, 999),
(26, 30, 999),
(27, 31, 999),
(28, 32, 999),
(29, 33, 999),
(30, 34, 999),
(31, 35, 999),
(32, 36, 999),
(33, 37, 999),
(34, 38, 999),
(36, 39, 999),
(37, 40, 999),
(38, 41, 999),
(39, 42, 999),
(40, 43, 999),
(41, 44, 999),
(42, 45, 999),
(43, 46, 999),
(44, 47, 999),
(45, 48, 999),
(46, 49, 999),
(47, 50, 999),
(48, 51, 999),
(49, 1, 999),
(50, 2, 999);

-- ============================================
-- 5. SONGS (30+ songs)
-- ============================================
INSERT INTO `songs` (`id`, `title`, `lyrics`, `status`, `viewed`) VALUES
-- Required specific song
(7329, 'Pogoda', 'Słońce świeci jasno nad miastem...', 999, 5000),
-- Additional songs for Top10
(1, 'Intro', 'Witamy w świecie hip-hopu...', 999, 8000),
(2, 'Ulice', 'Chodzę po ulicach miasta...', 999, 7500),
(3, 'Życie', 'Życie to jest walka codzienna...', 999, 7000),
(4, 'Przyjaciele', 'Moi przyjaciele są ze mną...', 999, 6500),
(5, 'Miłość', 'Miłość jest wszystkim co mam...', 999, 6000),
(6, 'Nadzieja', 'Nadzieja umiera ostatnia...', 999, 5500),
(7, 'Przyszłość', 'Przyszłość należy do nas...', 999, 5200),
(8, 'Przeszłość', 'Przeszłość nie wraca nigdy...', 999, 4900),
(9, 'Teraźniejszość', 'Żyj tu i teraz...', 999, 4600),
(10, 'Marzenia', 'Marzenia się spełniają...', 999, 4300),
(11, 'Sny', 'Sny są jak ścieżka...', 999, 4000),
(12, 'Walka', 'Walczymy o lepsze jutro...', 999, 3700),
(13, 'Zwycięstwo', 'Zwycięstwo jest nasze...', 999, 3400),
(14, 'Porażka', 'Z porażki się uczymy...', 999, 3100),
(15, 'Sukces', 'Sukces to ciężka praca...', 999, 2800),
(16, 'Rodzina', 'Rodzina jest najważniejsza...', 999, 2500),
(17, 'Dom', 'Dom jest tam gdzie serce...', 999, 2200),
(18, 'Miasto', 'Miasto nigdy nie śpi...', 999, 1900),
(19, 'Wieś', 'Na wsi jest spokój...', 999, 1600),
(20, 'Natura', 'Natura jest piękna...', 999, 1300),
(21, 'Muzyka', 'Muzyka łączy ludzi...', 999, 1000),
(22, 'Słowa', 'Słowa mają moc...', 999, 900),
(23, 'Rytm', 'Rytm bije w sercu...', 999, 800),
(24, 'Bit', 'Bit jest fundamentem...', 999, 700),
(25, 'Flow', 'Flow jest jak rzeka...', 999, 600),
(26, 'Tekst', 'Tekst opowiada historię...', 999, 500),
(27, 'Scena', 'Na scenie czuję się wolny...', 999, 400),
(28, 'Studio', 'W studiu tworzę magię...', 999, 300),
(29, 'Mikrofon', 'Mikrofon jest moją bronią...', 999, 200),
(30, 'Outro', 'To jest koniec albumu...', 999, 100);

-- ============================================
-- 6. ALBUM_LOOKUP (Links songs to albums with track numbers)
-- ============================================
INSERT INTO `album_lookup` (`songid`, `albumid`, `track`, `status`) VALUES
-- Song 7329 (Pogoda) on album 535 (Superextra) - Add multiple songs to test album
(7329, 535, 1, 999),
(1, 535, 2, 999),
(2, 535, 3, 999),
(3, 535, 4, 999),
(4, 535, 5, 999),
(5, 535, 6, 999),
-- Songs on album 1 (Jestem Hip Hopem)
(6, 1, 1, 999),
(7, 1, 2, 999),
(8, 1, 3, 999),
(9, 2, 1, 999),
(10, 2, 2, 999),
(11, 3, 1, 999),
(12, 3, 2, 999),
(13, 4, 1, 999),
(14, 4, 2, 999),
(15, 5, 1, 999),
(16, 5, 2, 999),
(17, 6, 1, 999),
(18, 6, 2, 999),
(19, 7, 1, 999),
(20, 7, 2, 999),
(21, 8, 1, 999),
(22, 8, 2, 999),
(23, 9, 1, 999),
(24, 9, 2, 999),
(25, 10, 1, 999),
(26, 10, 2, 999),
(27, 11, 1, 999),
(28, 11, 2, 999),
(29, 12, 1, 999),
(30, 12, 2, 999);

-- ============================================
-- 7. ARTIST_LOOKUP (Links songs to performing artists)
-- ============================================
INSERT INTO `artist_lookup` (`songid`, `artistid`, `status`) VALUES
(7329, 8, 999),  -- Wdowa performs Pogoda
(1, 1, 999),
(2, 1, 999),
(3, 2, 999),
(4, 2, 999),
(5, 3, 999),
(6, 9, 999),
(7, 10, 999),
(8, 11, 999),
(9, 12, 999),
(10, 13, 999),
(11, 14, 999),
(12, 15, 999),
(13, 16, 999),
(14, 17, 999),
(15, 18, 999),
(16, 19, 999),
(17, 20, 999),
(18, 21, 999),
(19, 22, 999),
(20, 23, 999);

-- ============================================
-- 8. FEATURE_LOOKUP (Featured artists on songs)
-- Required: Dj Technik and Beatmo on song 7329
-- ============================================
INSERT INTO `feature_lookup` (`songid`, `artistid`, `feattype`, `status`) VALUES
(7329, 6, 1, 999),  -- Dj Technik on Pogoda (scratch)
(7329, 7, 2, 999),  -- Beatmo on Pogoda (beat)
(1, 36, 1, 999),
(2, 37, 2, 999),
(3, 38, 1, 999),
(4, 39, 2, 999),
(5, 40, 1, 999);

-- ============================================
-- 9. FEATTYPES (Feature type definitions)
-- ============================================
INSERT INTO `feattypes` (`id`, `feattype`, `status`) VALUES
(1, 'scratch', 999),
(2, 'muzyka', 999),
(3, 'wokal', 999),
(4, 'produkcja', 999),
(5, 'gościnnie', 999);

-- ============================================
-- 10. NEWS (News articles)
-- Required: News ID 1877 with "Onar wraca z nowym singlem"
-- ============================================
INSERT INTO `news` (`ID`, `title`, `news`, `glyph`, `graph`, `viewed`, `added`) VALUES
(1877, 'ONAR - Jak na pierwszej płycie [wideo]', 'Onar wraca z nowym singlem promującym jego najnowszy album. Artysta prezentuje świeży materiał, który nawiązuje do jego wcześniejszej twórczości.', '', '', 2500, '2024-01-15 10:00:00'),
(1, 'Premiera nowego albumu Pezeta', 'Pezet zapowiada nowy album na wiosnę. Fani nie mogą się doczekać!', '', '', 3000, '2024-02-01 12:00:00'),
(2, 'Sokół wraca na scenę', 'Po dłuższej przerwie Sokół ogłasza trasę koncertową.', '', '', 2800, '2024-02-15 14:00:00'),
(3, 'Nowy teledysk Tedego', 'Tede prezentuje nowy klip do singla z nadchodzącego albumu.', '', '', 2600, '2024-03-01 10:00:00'),
(4, 'Paluch z płytą roku?', 'Krytycy zachwyceni nowym materiałem Palucha.', '', '', 2400, '2024-03-15 16:00:00'),
(5, 'Hemp Gru świętuje jubileusz', 'Hemp Gru obchodzi 25-lecie działalności specjalnym koncertem.', '', '', 2200, '2024-04-01 18:00:00');

-- ============================================
-- 11. HHB_USERS (Users for ratings)
-- ============================================
INSERT INTO `hhb_users` (`usr_id`, `usr_email`, `usr_password`, `usr_display_name`, `usr_is_admin`, `usr_added`, `usr_updated`, `usr_last_login`, `usr_login_count`) VALUES
(1, 'test1@example.com', MD5('password1'), 'TestUser1', 'no', NOW(), NOW(), NOW(), 10),
(2, 'test2@example.com', MD5('password2'), 'TestUser2', 'no', NOW(), NOW(), NOW(), 8),
(3, 'test3@example.com', MD5('password3'), 'TestUser3', 'no', NOW(), NOW(), NOW(), 6),
(4, 'test4@example.com', MD5('password4'), 'TestUser4', 'no', NOW(), NOW(), NOW(), 4),
(5, 'test5@example.com', MD5('password5'), 'TestUser5', 'no', NOW(), NOW(), NOW(), 2),
(6, 'test6@example.com', MD5('password6'), 'TestUser6', 'no', NOW(), NOW(), NOW(), 1),
(7, 'test7@example.com', MD5('password7'), 'TestUser7', 'no', NOW(), NOW(), NOW(), 1),
(8, 'test8@example.com', MD5('password8'), 'TestUser8', 'no', NOW(), NOW(), NOW(), 1),
(9, 'test9@example.com', MD5('password9'), 'TestUser9', 'no', NOW(), NOW(), NOW(), 1),
(10, 'admin@example.com', MD5('adminpass'), 'Admin', 'yes', NOW(), NOW(), NOW(), 100);

-- ============================================
-- 12. RATINGS (Individual user ratings)
-- ============================================
INSERT INTO `ratings` (`ID`, `albumid`, `userid`, `rate`, `added`) VALUES
(1, 535, 1, 5, NOW()),
(2, 535, 2, 4, NOW()),
(3, 535, 3, 5, NOW()),
(4, 1, 1, 5, NOW()),
(5, 1, 2, 4, NOW()),
(6, 1, 3, 5, NOW()),
(7, 2, 1, 4, NOW()),
(8, 2, 2, 5, NOW()),
(9, 3, 1, 5, NOW()),
(10, 3, 3, 4, NOW()),
(11, 4, 2, 5, NOW()),
(12, 4, 4, 4, NOW()),
(13, 5, 1, 4, NOW()),
(14, 5, 5, 5, NOW()),
(15, 6, 2, 5, NOW()),
(16, 6, 6, 4, NOW()),
(17, 7, 3, 4, NOW()),
(18, 7, 7, 5, NOW()),
(19, 8, 4, 5, NOW()),
(20, 8, 8, 4, NOW()),
(21, 9, 5, 4, NOW()),
(22, 9, 9, 5, NOW()),
(23, 10, 6, 5, NOW()),
(24, 10, 10, 4, NOW()),
(25, 11, 1, 4, NOW()),
(26, 11, 2, 5, NOW()),
(27, 12, 3, 5, NOW()),
(28, 12, 4, 4, NOW()),
(29, 13, 5, 4, NOW()),
(30, 13, 6, 5, NOW()),
(31, 14, 7, 5, NOW()),
(32, 14, 8, 4, NOW()),
(33, 15, 9, 4, NOW()),
(34, 15, 10, 5, NOW()),
(35, 16, 1, 5, NOW()),
(36, 16, 2, 4, NOW()),
(37, 17, 3, 4, NOW()),
(38, 17, 4, 5, NOW()),
(39, 18, 5, 5, NOW()),
(40, 18, 6, 4, NOW()),
(41, 19, 7, 4, NOW()),
(42, 19, 8, 5, NOW()),
(43, 20, 9, 5, NOW()),
(44, 20, 10, 4, NOW()),
(45, 21, 1, 4, NOW()),
(46, 21, 2, 5, NOW()),
(47, 22, 3, 5, NOW()),
(48, 22, 4, 4, NOW()),
(49, 23, 5, 4, NOW()),
(50, 23, 6, 5, NOW()),
(51, 24, 7, 5, NOW()),
(52, 24, 8, 4, NOW()),
(53, 25, 9, 4, NOW()),
(54, 25, 10, 5, NOW()),
(55, 26, 1, 5, NOW()),
(56, 26, 2, 4, NOW()),
(57, 27, 3, 4, NOW()),
(58, 27, 4, 5, NOW()),
(59, 28, 5, 5, NOW()),
(60, 28, 6, 4, NOW()),
(61, 29, 7, 4, NOW()),
(62, 29, 8, 5, NOW()),
(63, 30, 9, 5, NOW()),
(64, 30, 10, 4, NOW()),
(65, 31, 1, 4, NOW()),
(66, 31, 2, 5, NOW()),
(67, 32, 3, 5, NOW()),
(68, 32, 4, 4, NOW()),
(69, 33, 5, 4, NOW()),
(70, 33, 6, 5, NOW()),
(71, 34, 7, 5, NOW()),
(72, 34, 8, 4, NOW()),
(73, 36, 9, 4, NOW()),
(74, 36, 10, 5, NOW()),
(75, 37, 1, 5, NOW()),
(76, 37, 2, 4, NOW()),
(77, 38, 3, 4, NOW()),
(78, 38, 4, 5, NOW()),
(79, 39, 5, 5, NOW()),
(80, 39, 6, 4, NOW()),
(81, 40, 7, 4, NOW()),
(82, 40, 8, 5, NOW()),
(83, 41, 9, 5, NOW()),
(84, 41, 10, 4, NOW()),
(85, 42, 1, 4, NOW()),
(86, 42, 2, 5, NOW()),
(87, 43, 3, 5, NOW()),
(88, 43, 4, 4, NOW()),
(89, 44, 5, 4, NOW()),
(90, 44, 6, 5, NOW()),
(91, 45, 7, 5, NOW()),
(92, 45, 8, 4, NOW()),
(93, 46, 9, 4, NOW()),
(94, 46, 10, 5, NOW()),
(95, 47, 1, 5, NOW()),
(96, 47, 2, 4, NOW()),
(97, 48, 3, 4, NOW()),
(98, 48, 4, 5, NOW()),
(99, 49, 5, 5, NOW()),
(100, 49, 6, 4, NOW());

-- ============================================
-- 13. RATINGS_AVG (Average ratings for Top10)
-- ============================================
INSERT INTO `ratings_avg` (`albumid`, `rating`) VALUES
(535, 4.7),
(1, 4.7),
(2, 4.5),
(3, 4.5),
(4, 4.5),
(5, 4.5),
(6, 4.5),
(7, 4.5),
(8, 4.5),
(9, 4.5),
(10, 4.5),
(11, 4.5),
(12, 4.5),
(13, 4.5),
(14, 4.5),
(15, 4.5),
(16, 4.5),
(17, 4.5),
(18, 4.5),
(19, 4.5),
(20, 4.5),
(21, 4.5),
(22, 4.5),
(23, 4.5),
(24, 4.5),
(25, 4.5),
(26, 4.5),
(27, 4.5),
(28, 4.5),
(29, 4.5),
(30, 4.5),
(31, 4.5),
(32, 4.5),
(33, 4.5),
(34, 4.5),
(36, 4.5),
(37, 4.5),
(38, 4.5),
(39, 4.5),
(40, 4.5),
(41, 4.5),
(42, 4.5),
(43, 4.5),
(44, 4.5),
(45, 4.5),
(46, 4.5),
(47, 4.5),
(48, 4.5),
(49, 4.5),
(50, 4.5);

-- ============================================
-- 14. SEARCHES (Popular searches for Top10)
-- ============================================
INSERT INTO `searches` (`ID`, `searchstring`, `userid`, `added`) VALUES
(1, 'pezet', NULL, NOW()),
(2, 'tede', NULL, NOW()),
(3, 'sokół', NULL, NOW()),
(4, 'paluch', NULL, NOW()),
(5, 'eldo', NULL, NOW()),
(6, 'peja', NULL, NOW()),
(7, 'ostr', NULL, NOW()),
(8, 'hemp gru', NULL, NOW()),
(9, 'grammatik', NULL, NOW()),
(10, 'paktofonika', NULL, NOW()),
(11, 'kali', NULL, NOW()),
(12, 'grubson', NULL, NOW()),
(13, 'quebonafide', NULL, NOW()),
(14, 'taco hemingway', NULL, NOW()),
(15, 'młody wilk', NULL, NOW());

-- ============================================
-- 15. BAND_LOOKUP (Band members for Top10 project albums)
-- ============================================
INSERT INTO `band_lookup` (`artistid`, `bandid`, `status`) VALUES
-- Grammatik members
(2, 22, 999),  -- Eldo in Grammatik
-- Paktofonika members
(35, 23, 999),  -- Mes in Paktofonika
-- Molesta members
(16, 24, 999),  -- Włodi in Molesta
-- WWO members
(17, 25, 999),  -- Vienio in WWO
-- Hemp Gru members
(21, 26, 999),  -- Donguralesko in Hemp Gru
-- Slums Attack members
(12, 27, 999),  -- Peja in Slums Attack
-- Kaliber 44 members
(18, 28, 999),  -- Abradab in Kaliber 44
-- Additional band relationships
(1, 22, 999),
(10, 27, 999),
(11, 23, 999),
(19, 24, 999),
(20, 25, 999),
(29, 26, 999),
(30, 27, 999),
(31, 28, 999),
(32, 22, 999),
(33, 23, 999),
(34, 24, 999),
(36, 25, 999),
(37, 26, 999),
(38, 27, 999);

-- ============================================
-- 16. ALTNAMES_LOOKUP (Alternative artist names for search)
-- ============================================
INSERT INTO `altnames_lookup` (`artistid`, `altname`, `status`) VALUES
(4, 'Tede', 999),
(5, 'Tede', 999),
(9, 'Mefistotedes', 999),
(9, 'MercTedes', 999),
(14, 'Ostry', 999),
(14, 'OSTR', 999);

-- ============================================
-- 17. ARTISTS_PHOTOS (Artist photo files)
-- ============================================
INSERT INTO `artists_photos` (`id`, `artistid`, `filename`, `description`, `main`, `source`, `sourceurl`, `addedby`, `added`) VALUES
(1, 1, 'test-artist-001.jpg', 'Zdjęcie testowe Pezeta', 'y', '', '', 1, NOW()),
(2, 2, 'test-artist-002.jpg', 'Zdjęcie testowe Eldo', 'y', '', '', 1, NOW()),
(3, 3, 'test-artist-003.jpg', 'Zdjęcie testowe Stasiaka', 'y', '', '', 1, NOW()),
(4, 9, 'test-artist-004.jpg', 'Zdjęcie testowe Tedego', 'y', '', '', 1, NOW()),
(5, 10, 'test-artist-005.jpg', 'Zdjęcie testowe Sokoła', 'y', '', '', 1, NOW()),
(6, 11, 'test-artist-006.jpg', 'Zdjęcie testowe Palucha', 'y', '', '', 1, NOW()),
(7, 12, 'test-artist-007.jpg', 'Zdjęcie testowe Pei', 'y', '', '', 1, NOW()),
(8, 13, 'test-artist-008.jpg', 'Zdjęcie testowe Onara', 'y', '', '', 1, NOW()),
(9, 14, 'test-artist-009.jpg', 'Zdjęcie testowe O.S.T.R.', 'y', '', '', 1, NOW()),
(10, 15, 'test-artist-010.jpg', 'Zdjęcie testowe Kaliego', 'y', '', '', 1, NOW()),
(11, 16, 'test-artist-011.jpg', 'Zdjęcie testowe Włodiego', 'y', '', '', 1, NOW()),
(12, 17, 'test-artist-012.jpg', 'Zdjęcie testowe Vienia', 'y', '', '', 1, NOW()),
(13, 18, 'test-artist-013.jpg', 'Zdjęcie testowe Abradaba', 'y', '', '', 1, NOW()),
(14, 19, 'test-artist-014.jpg', 'Zdjęcie testowe Łony', 'y', '', '', 1, NOW()),
(15, 20, 'test-artist-015.jpg', 'Zdjęcie testowe Zeusa', 'y', '', '', 1, NOW()),
(16, 21, 'test-artist-016.jpg', 'Zdjęcie testowe Donguralesko', 'y', '', '', 1, NOW()),
(17, 35, 'test-artist-017.jpg', 'Zdjęcie testowe Mesa', 'y', '', '', 1, NOW()),
(18, 29, 'test-artist-018.jpg', 'Zdjęcie testowe Zipery', 'y', '', '', 1, NOW()),
(19, 30, 'test-artist-019.jpg', 'Zdjęcie testowe Bisza', 'y', '', '', 1, NOW()),
(20, 31, 'test-artist-020.jpg', 'Zdjęcie testowe Rahima', 'y', '', '', 1, NOW()),
(21, 32, 'test-artist-021.jpg', 'Zdjęcie testowe Grubsona', 'y', '', '', 1, NOW()),
(22, 33, 'test-artist-022.jpg', 'Zdjęcie testowe Proceente', 'y', '', '', 1, NOW()),
(23, 34, 'test-artist-023.jpg', 'Zdjęcie testowe Piha', 'y', '', '', 1, NOW()),
(24, 45, 'test-artist-024.jpg', 'Zdjęcie testowe Kaza Bałagane', 'y', '', '', 1, NOW()),
(25, 46, 'test-artist-025.jpg', 'Zdjęcie testowe Miuosha', 'y', '', '', 1, NOW()),
(26, 47, 'test-artist-026.jpg', 'Zdjęcie testowe Ten Typ Mesa', 'y', '', '', 1, NOW()),
(27, 48, 'test-artist-027.jpg', 'Zdjęcie testowe Quebonafide', 'y', '', '', 1, NOW()),
(28, 49, 'test-artist-028.jpg', 'Zdjęcie testowe Taco Hemingwaya', 'y', '', '', 1, NOW()),
(29, 50, 'test-artist-029.jpg', 'Zdjęcie testowe Żabsona', 'y', '', '', 1, NOW()),
(30, 51, 'test-artist-030.jpg', 'Zdjęcie testowe Borixona', 'y', '', '', 1, NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- End of test fixtures
-- ============================================
