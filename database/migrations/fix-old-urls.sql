-- ============================================================================
-- Migration: Fix Old URLs in Database
-- Description: Updates old URL formats (e.g., hhbd.pl/n/slug) to new SEO-friendly
--              format (e.g., hhbd.pl/slug-pXX.html, hhbd.pl/slug-lXX.html)
-- Date: 2026-01-12
-- Database: MariaDB 10.11
-- ============================================================================

-- This migration script updates text fields that contain old URL patterns.
-- The old URLs use format: https://hhbd.pl/n/{slug}
-- The new URLs use format: https://hhbd.pl/{slug}-{type}{id}.html
-- Where type is: p=artist, l=label, a=album, s=song, n=news

-- IMPORTANT: This script should be run AFTER creating a full database backup!

-- ============================================================================
-- PART 1: Identify URLs that need fixing
-- ============================================================================

-- Query to find all tables and fields containing old-format URLs
-- Run this BEFORE the migration to understand the scope

SELECT 'albums' as table_name, 'description' as field_name, COUNT(*) as count
FROM albums WHERE description LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'albums', 'artistabout', COUNT(*)
FROM albums WHERE artistabout LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'albums', 'notes', COUNT(*)
FROM albums WHERE notes LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'artists', 'profile', COUNT(*)
FROM artists WHERE profile LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'artists', 'concertinfo', COUNT(*)
FROM artists WHERE concertinfo LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'artists', 'trivia', COUNT(*)
FROM artists WHERE trivia LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'labels', 'profile', COUNT(*)
FROM labels WHERE profile LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'songs', 'description', COUNT(*)
FROM songs WHERE description LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'news', 'news', COUNT(*)
FROM news WHERE news LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'artists_photos', 'description', COUNT(*)
FROM artists_photos WHERE description LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'hhb_comments', 'com_content', COUNT(*)
FROM hhb_comments WHERE com_content LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'cities', 'description', COUNT(*)
FROM cities WHERE description LIKE '%hhbd.pl/n/%';

-- ============================================================================
-- PART 2: Simple URL replacement (removes /n/ prefix)
-- ============================================================================

-- This is a safe first step that removes the /n/ prefix from all URLs
-- The URLs will become https://hhbd.pl/{slug} which is still not perfect
-- but closer to the correct format

-- WARNING: The following updates are destructive. Make sure you have a backup!

/*
-- Update albums table
UPDATE albums SET description = REPLACE(description, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE description LIKE '%https://hhbd.pl/n/%';

UPDATE albums SET description = REPLACE(description, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE description LIKE '%http://hhbd.pl/n/%';

UPDATE albums SET artistabout = REPLACE(artistabout, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE artistabout LIKE '%https://hhbd.pl/n/%';

UPDATE albums SET artistabout = REPLACE(artistabout, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE artistabout LIKE '%http://hhbd.pl/n/%';

UPDATE albums SET notes = REPLACE(notes, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE notes LIKE '%https://hhbd.pl/n/%';

UPDATE albums SET notes = REPLACE(notes, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE notes LIKE '%http://hhbd.pl/n/%';

-- Update artists table
UPDATE artists SET profile = REPLACE(profile, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE profile LIKE '%https://hhbd.pl/n/%';

UPDATE artists SET profile = REPLACE(profile, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE profile LIKE '%http://hhbd.pl/n/%';

UPDATE artists SET concertinfo = REPLACE(concertinfo, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE concertinfo LIKE '%https://hhbd.pl/n/%';

UPDATE artists SET concertinfo = REPLACE(concertinfo, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE concertinfo LIKE '%http://hhbd.pl/n/%';

UPDATE artists SET trivia = REPLACE(trivia, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE trivia LIKE '%https://hhbd.pl/n/%';

UPDATE artists SET trivia = REPLACE(trivia, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE trivia LIKE '%http://hhbd.pl/n/%';

-- Update labels table
UPDATE labels SET profile = REPLACE(profile, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE profile LIKE '%https://hhbd.pl/n/%';

UPDATE labels SET profile = REPLACE(profile, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE profile LIKE '%http://hhbd.pl/n/%';

-- Update songs table
UPDATE songs SET description = REPLACE(description, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE description LIKE '%https://hhbd.pl/n/%';

UPDATE songs SET description = REPLACE(description, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE description LIKE '%http://hhbd.pl/n/%';

-- Update news table
UPDATE news SET news = REPLACE(news, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE news LIKE '%https://hhbd.pl/n/%';

UPDATE news SET news = REPLACE(news, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE news LIKE '%http://hhbd.pl/n/%';

-- Update artists_photos table
UPDATE artists_photos SET description = REPLACE(description, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE description LIKE '%https://hhbd.pl/n/%';

UPDATE artists_photos SET description = REPLACE(description, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE description LIKE '%http://hhbd.pl/n/%';

-- Update hhb_comments table
UPDATE hhb_comments SET com_content = REPLACE(com_content, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE com_content LIKE '%https://hhbd.pl/n/%';

UPDATE hhb_comments SET com_content = REPLACE(com_content, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE com_content LIKE '%http://hhbd.pl/n/%';

-- Update cities table
UPDATE cities SET description = REPLACE(description, 'https://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE description LIKE '%https://hhbd.pl/n/%';

UPDATE cities SET description = REPLACE(description, 'http://hhbd.pl/n/', 'https://hhbd.pl/')
WHERE description LIKE '%http://hhbd.pl/n/%';
*/

-- ============================================================================
-- PART 3: Verification
-- ============================================================================

-- After running the migration, verify no old-format URLs remain
-- This query should return 0 rows for each table/field

/*
SELECT 'albums' as table_name, 'description' as field_name, COUNT(*) as remaining_old_urls
FROM albums WHERE description LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'albums', 'artistabout', COUNT(*)
FROM albums WHERE artistabout LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'albums', 'notes', COUNT(*)
FROM albums WHERE notes LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'artists', 'profile', COUNT(*)
FROM artists WHERE profile LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'artists', 'concertinfo', COUNT(*)
FROM artists WHERE concertinfo LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'artists', 'trivia', COUNT(*)
FROM artists WHERE trivia LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'labels', 'profile', COUNT(*)
FROM labels WHERE profile LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'songs', 'description', COUNT(*)
FROM songs WHERE description LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'news', 'news', COUNT(*)
FROM news WHERE news LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'artists_photos', 'description', COUNT(*)
FROM artists_photos WHERE description LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'hhb_comments', 'com_content', COUNT(*)
FROM hhb_comments WHERE com_content LIKE '%hhbd.pl/n/%'
UNION ALL
SELECT 'cities', 'description', COUNT(*)
FROM cities WHERE description LIKE '%hhbd.pl/n/%';
*/
