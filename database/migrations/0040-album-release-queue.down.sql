-- The release before #258 knows no queue: an album still waiting stays unpublished, as one
-- that lacks a part does, and an artist still waiting is shown, as it was before. The job's
-- releases stay in the journal, as made on the command line.
UPDATE edit_operations SET path = 'cli' WHERE path = 'job';
COMMENT ON COLUMN edit_operations.user_id IS NULL;
ALTER TABLE edit_operations DROP CONSTRAINT edit_operations_path_check;
ALTER TABLE edit_operations ADD CONSTRAINT edit_operations_path_check CHECK (path IN ('cli', 'panel'));

DROP INDEX i_artists_queued;
ALTER TABLE artists DROP COLUMN queued;

DROP INDEX i_albums_queued;
ALTER TABLE albums DROP COLUMN queued;
