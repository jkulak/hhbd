-- 0035 captcha-challenges: the comment form's question, held by the server and answerable once
-- (#41). The form asked "Ile to 3 + 4?" and sent md5(answer . salt) along, with the salt in
-- the code, so a bot could make its own pair and replay one forever. Now the script asks for a
-- question when someone starts a comment, the answer stays here under a random token, and
-- the comment's check deletes the row whatever the answer. Rows older than an hour go.
CREATE TABLE `captcha_challenges` (
  `token` char(32) NOT NULL,
  `answer` tinyint(3) unsigned NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token`),
  KEY `i_captcha_challenges_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
