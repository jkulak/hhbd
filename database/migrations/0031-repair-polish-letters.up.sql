-- 0031 repair-polish-letters: up. Polish letters and punctuation that an old import or the old
-- backoffice stored mangled (#27): UTF-8 read as a single-byte code page and stored again, once
-- or several times over (JÄ¹ąW for JŹW, PÃ“Ä¹ąNIEJ for PÓŹNIEJ), and single-byte text read as
-- latin-1 (W³a¶nie for Właśnie). The sequences and letters below are the ones a scan of
-- production on 2026-10-09 found, column by column; no value from the database is in this file,
-- only what is replaced, and the keys of the rows a person looked at.
--
-- Literals are written as UTF-8 bytes (X'...') in the binary collation, so the file is ASCII and
-- a replacement matches exactly what it names. A sequence comes off
-- longest first, and a column runs the replacements again for each level of mangling it had.
--
-- No archive and no down, unlike every other migration that changes rows: the old values were
-- mojibake, and #27 was decided on 2026-10-09 as a quick cleanup that keeps no copy of them.

-- albums.description: 9 sequences, 2 level(s)
UPDATE `albums` SET `description` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`description`, CONVERT(X'c3a2e282acc29d' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e2809d' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c3a2e282acc2a6' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e280a6' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c3a2e282acc5be' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e2809e' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c3a2e282ace284a2' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e28099' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c382e2809e' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c284' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c383e2809a' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c382' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c384c284' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c484' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c384e2809a' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c482' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c482e2809e' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c384' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c3a2e282acc29d' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c3a2e282acc2a6' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c3a2e282acc5be' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c3a2e282ace284a2' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c382e2809e' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c383e2809a' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384c284' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384e2809a' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c482e2809e' USING utf8mb4) COLLATE utf8mb4_bin, '%');
UPDATE `albums` SET `description` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`description`, CONVERT(X'c3a2e282acc29d' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e2809d' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c3a2e282acc2a6' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e280a6' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c3a2e282acc5be' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e2809e' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c3a2e282ace284a2' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e28099' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c382e2809e' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c284' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c383e2809a' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c382' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c384c284' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c484' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c384e2809a' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c482' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c482e2809e' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c384' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c3a2e282acc29d' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c3a2e282acc2a6' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c3a2e282acc5be' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c3a2e282ace284a2' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c382e2809e' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c383e2809a' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384c284' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384e2809a' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `description` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c482e2809e' USING utf8mb4) COLLATE utf8mb4_bin, '%');

-- altnames_lookup.altname: 2 sequences, 1 level(s)
UPDATE `altnames_lookup` SET `altname` = REPLACE(REPLACE(`altname`, CONVERT(X'c384c2b9' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c4b9' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c4b9c485' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c5b9' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE `altname` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384c2b9' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `altname` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c4b9c485' USING utf8mb4) COLLATE utf8mb4_bin, '%');

-- artists.profile: 7 sequences, 1 level(s)
UPDATE `artists` SET `profile` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`profile`, CONVERT(X'c3a2e282acc5be' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e2809e' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c393' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c383e2809e' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c384' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c384c2b9' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c4b9' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c384e2809e' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c484' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c385c2b9' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c5b9' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c4b9c485' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c5b9' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE `profile` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c3a2e282acc5be' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `profile` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `profile` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c383e2809e' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `profile` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384c2b9' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `profile` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384e2809e' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `profile` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c385c2b9' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `profile` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c4b9c485' USING utf8mb4) COLLATE utf8mb4_bin, '%');

-- cities.name: 5 sequences, 1 level(s)
UPDATE `cities` SET `name` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`name`, CONVERT(X'c382c2a9' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c2a9' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c384e2809a' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c482' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c38be280a1' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'cb87' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c482c2a9' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c3a9' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c482cb87' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c3a1' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE `name` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c382c2a9' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `name` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384e2809a' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `name` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c38be280a1' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `name` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c482c2a9' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `name` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c482cb87' USING utf8mb4) COLLATE utf8mb4_bin, '%');

-- news.news: 1 sequences, 1 level(s)
UPDATE `news` SET `news` = REPLACE(`news`, CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c393' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE `news` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, '%');

-- news.title: 6 sequences, 1 level(s)
UPDATE `news` SET `title` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`title`, CONVERT(X'c3a2e282acc5be' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e2809e' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c393' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c383e2809e' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c384' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c384c2b9' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c4b9' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c384e2809e' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c484' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c4b9c485' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c5b9' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE `title` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c3a2e282acc5be' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `title` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `title` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c383e2809e' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `title` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384c2b9' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `title` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384e2809e' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `title` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c4b9c485' USING utf8mb4) COLLATE utf8mb4_bin, '%');

-- songs.title: 3 sequences, 1 level(s)
UPDATE `songs` SET `title` = REPLACE(REPLACE(REPLACE(`title`, CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c393' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c384c2b9' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c4b9' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c4b9c485' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c5b9' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE `title` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `title` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c384c2b9' USING utf8mb4) COLLATE utf8mb4_bin, '%') OR `title` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c4b9c485' USING utf8mb4) COLLATE utf8mb4_bin, '%');

-- submision_errors.message: 1 sequences, 1 level(s)
UPDATE `submision_errors` SET `message` = REPLACE(`message`, CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c393' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE `message` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, '%');

-- users.place: 1 sequences, 1 level(s)
UPDATE `users` SET `place` = REPLACE(`place`, CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c393' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE `place` COLLATE utf8mb4_bin LIKE CONCAT('%', CONVERT(X'c383e2809c' USING utf8mb4) COLLATE utf8mb4_bin, '%');

-- songs.title: iso letters read as latin-1, in 53 rows a person looked at
UPDATE `songs` SET `title` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`title`, CONVERT(X'c2a1' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c484' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c2a3' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c581' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c2a6' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c59a' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c2ac' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c5b9' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c2af' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c5bb' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c2b1' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c485' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c2b3' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c582' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c2b6' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c59b' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c2bc' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c5ba' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c2bf' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c5bc' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c386' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c486' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c38a' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c498' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c391' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c583' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c3a6' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c487' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c3aa' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c499' USING utf8mb4) COLLATE utf8mb4_bin), CONVERT(X'c3b1' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c584' USING utf8mb4) COLLATE utf8mb4_bin)
  WHERE (`id` = 6182)
     OR (`id` = 6190)
     OR (`id` = 6192)
     OR (`id` = 6194)
     OR (`id` = 6207)
     OR (`id` = 6210)
     OR (`id` = 6211)
     OR (`id` = 6212)
     OR (`id` = 6215)
     OR (`id` = 6218)
     OR (`id` = 6277)
     OR (`id` = 6280)
     OR (`id` = 6283)
     OR (`id` = 6289)
     OR (`id` = 6291)
     OR (`id` = 6292)
     OR (`id` = 6296)
     OR (`id` = 6297)
     OR (`id` = 6298)
     OR (`id` = 6300)
     OR (`id` = 6329)
     OR (`id` = 6335)
     OR (`id` = 6338)
     OR (`id` = 6344)
     OR (`id` = 6346)
     OR (`id` = 6348)
     OR (`id` = 6349)
     OR (`id` = 6350)
     OR (`id` = 6352)
     OR (`id` = 6469)
     OR (`id` = 6471)
     OR (`id` = 6474)
     OR (`id` = 6476)
     OR (`id` = 6480)
     OR (`id` = 6484)
     OR (`id` = 6485)
     OR (`id` = 6487)
     OR (`id` = 6513)
     OR (`id` = 6549)
     OR (`id` = 6667)
     OR (`id` = 6670)
     OR (`id` = 6672)
     OR (`id` = 6674)
     OR (`id` = 6677)
     OR (`id` = 6678)
     OR (`id` = 6703)
     OR (`id` = 6709)
     OR (`id` = 6711)
     OR (`id` = 6713)
     OR (`id` = 6714)
     OR (`id` = 6716)
     OR (`id` = 6717)
     OR (`id` = 7609);

-- albums.description: control characters left behind by an earlier cleanup by hand
UPDATE `albums` SET `description` = REGEXP_REPLACE(`description`, '[\\x{80}-\\x{9F}]', '') WHERE `description` REGEXP '[\\x{80}-\\x{9F}]';

-- hhb_comments.com_author: control characters left behind by an earlier cleanup by hand
UPDATE `hhb_comments` SET `com_author` = REGEXP_REPLACE(`com_author`, '[\\x{80}-\\x{9F}]', '') WHERE `com_author` REGEXP '[\\x{80}-\\x{9F}]';

-- hhb_comments.com_content: control characters left behind by an earlier cleanup by hand
UPDATE `hhb_comments` SET `com_content` = REGEXP_REPLACE(`com_content`, '[\\x{80}-\\x{9F}]', '') WHERE `com_content` REGEXP '[\\x{80}-\\x{9F}]';

-- news.news: control characters left behind by an earlier cleanup by hand
UPDATE `news` SET `news` = REGEXP_REPLACE(`news`, '[\\x{80}-\\x{9F}]', '') WHERE `news` REGEXP '[\\x{80}-\\x{9F}]';

-- altnames_lookup.altname: by hand
UPDATE `altnames_lookup` SET `altname` = REPLACE(`altname`, CONVERT(X'2623383232303b' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e2809c' USING utf8mb4) COLLATE utf8mb4_bin) WHERE (`artistid` = 703 AND `status` = 999);

-- artists.profile: by hand
UPDATE `artists` SET `profile` = REPLACE(`profile`, CONVERT(X'c5b9c3b36465c582' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'c5b972c3b36465c582' USING utf8mb4) COLLATE utf8mb4_bin) WHERE (`id` = 819);

-- songs.title: by hand
UPDATE `songs` SET `title` = REPLACE(`title`, CONVERT(X'2623383231363b' USING utf8mb4) COLLATE utf8mb4_bin, CONVERT(X'e28098' USING utf8mb4) COLLATE utf8mb4_bin) WHERE (`id` = 159);
