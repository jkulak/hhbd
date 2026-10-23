-- When each album was published (#221), the lastmod of its page and of its songs' pages in the
-- sitemaps: updated says when the row changed, which its page does not follow, and added when it
-- came in, which a held-back album's page does not either. Whoever publishes an album sets it,
-- on the application's clock as the day's release sets added, and whoever takes one back clears
-- it; no trigger does, as none changes a row behind the application (#48).
ALTER TABLE albums ADD COLUMN published timestamp(0);
COMMENT ON COLUMN albums.published IS 'when the album was published, on the application''s clock, Europe/Warsaw (#221); NULL while it is not, or where nothing kept the moment';

-- What is known of the albums published so far: the latest of when each came in, an edit that
-- published it and was not undone, and the review item it was published or completed with. The
-- last two are on the database's clock, UTC.
UPDATE albums a SET published = greatest(
    a.added,
    (SELECT max(o.created) FROM edit_journal j JOIN edit_operations o ON o.id = j.operation_id
      WHERE j.table_name = 'albums' AND o.undone_by IS NULL AND (j.row_after->>'id')::int = a.id
        AND (j.row_after->>'status')::int = 999 AND (j.row_before->>'status')::int IS DISTINCT FROM 999)
      AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Warsaw',
    (SELECT max(r.resolved) FROM review_items r
      WHERE r.entity_type = 'album' AND r.entity_id = a.id AND r.resolution IN ('published', 'completed'))
      AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Warsaw')
WHERE a.status = 999;
