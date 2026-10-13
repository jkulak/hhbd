-- The release queue (#258): an album an import would publish waits in it, unpublished, and the
-- daily release job publishes the next few, so the catalogue grows a little every day rather
-- than by a year at once. queued is when the album joined; NULL for every album not waiting.
ALTER TABLE albums ADD COLUMN queued timestamp(0);
COMMENT ON COLUMN albums.queued IS 'when an import put the album in the release queue (#258); NULL when it is not waiting';
CREATE INDEX i_albums_queued ON albums (queued, year, id) WHERE queued IS NOT NULL;

-- An artist the import makes waits with the album it came with, until a release shows it. Its
-- own column, as status 0 already marks 456 artists of 2004 that the pages show all the same.
ALTER TABLE artists ADD COLUMN queued timestamp(0);
COMMENT ON COLUMN artists.queued IS 'when an import made the artist, waiting for the release of an album it comes with (#258); NULL once shown';
CREATE INDEX i_artists_queued ON artists (queued) WHERE queued IS NOT NULL;

-- The job writes each release in the edit journal, so make ovh-edit undoes one like any edit.
ALTER TABLE edit_operations DROP CONSTRAINT edit_operations_path_check;
ALTER TABLE edit_operations ADD CONSTRAINT edit_operations_path_check CHECK (path IN ('cli', 'panel', 'job'));
COMMENT ON COLUMN edit_operations.user_id IS 'the admin''s hhb_users account (cli, panel), or the users row a job writes as (job)';
