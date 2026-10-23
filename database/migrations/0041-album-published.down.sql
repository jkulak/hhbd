-- The release before #221 has no lastmod in its sitemaps and reads no published.
ALTER TABLE albums DROP COLUMN published;
