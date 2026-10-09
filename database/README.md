# The database

How the schema is defined and changed, and how a local database is set up.

```
database/
├── migrations/
│   ├── 0001-baseline.up.sql     # the schema as it was when the migrations began
│   ├── 0001-baseline.down.sql   # drops it
│   └── NNNN-slug.{up,down}.sql  # every change since, in order
├── tests/
│   └── fixtures.sql             # deterministic test data for the smoke test, on the baseline
├── dev/
│   └── init.sql                 # a production dump for local work; git-ignored, optional
└── README.md
```

## Setting a local database up

With the stack running (`docker compose up -d`):

```bash
make reset-db
```

It drops the `hhbd` database and builds it again the way production's is: the baseline schema
from the migrations, the fixtures loaded onto it, and every later migration run over that data.
It takes a few seconds, leaves the containers and the volume alone, and ends with the exact table
and row counts. Run it before and after a piece of work, so the database never carries what the
last one left. CI sets its database up the same way, so what passes here passes there.

It acts only on the running db container of this checkout's compose project, on the local
Docker engine, and refuses anything else before a statement reaches a database: a Docker
context or `DOCKER_HOST` that is not a local socket, a project with no running db, or a db
container started from another directory or from `deploy/compose.ovh.yaml`.
`make test-reset-db` checks all of that against the running stack; CI runs it after the smoke
test.

For production-like data instead, put a dump at `database/dev/init.sql` (git-ignored; never
commit it), mount `database/dev` as the db container's `/docker-entrypoint-initdb.d` in your
`compose.override.yaml`, and start with an empty volume (`docker compose down -v && docker
compose up -d`). A dump has the schema but no record of it, so before anything else:

```bash
make migrate-baseline   # records 0001-baseline as applied, after checking the schema matches
make migrate            # applies what came after it
```

## Migrations

Every change to the schema, and every change to data that production needs, is a migration:
two plain SQL files in `database/migrations/`, numbered in order, applied once and recorded in
the table `schema_migrations` of the database itself.

```bash
make migrate-new NAME=add-album-isrc   # 0002-add-album-isrc.up.sql and .down.sql, to fill in
make migrate                           # apply what is pending to the local database
make migrate-down                      # revert the last one; N=2 for the last two, N=all for every one
make migrate-status                    # what is applied when, what is pending
```

Rules the runner (`scripts/migrate.sh`) enforces:

- **Every migration has a down**, and the down undoes exactly what the up did. A migration
  without a down file is refused before anything runs.
- **One small change per migration.** MariaDB commits DDL as it goes, so a file that fails
  halfway has done part of its work and is not recorded; the runner stops there, says which file
  failed, and nothing after it runs. Undo by hand what did run, fix the file, run again. Small
  files make that a minute's work.
- **A migration must leave the running release working.** On the OVH host, `ci-deploy` takes a
  release that does not become healthy back to the one before, which then runs against whatever
  the migration did (CONTRACT.md §6 in `gcloud-ovh-migrate`). So: add a column in one release,
  use it in the next, drop the old one in a third, never all three at once.
- **Slugs** are lowercase letters, digits and dashes; **versions** four digits, given by
  `make migrate-new`.
- Files run as root inside the db container with `--default-character-set=utf8mb4`; a migration
  that writes Polish text needs no `SET NAMES` of its own.

And one rule `make test-schema` holds: **every table is InnoDB**, since 0005 (#45). A new table
says `ENGINE=InnoDB`; MyISAM has no crash recovery, locks a whole table per write, and gives the
nightly `mysqldump --single-transaction` no consistent snapshot.

The fixtures are written for the baseline schema. `make reset-db` loads them onto the baseline
and then runs the later migrations over them, exactly as production's data lives, so a migration
that cannot cope with real rows fails here first. A migration that changes a table the fixtures
fill does not need the fixtures changed, unless the smoke test should see something new.

`make test-migrate` exercises all of it against the running stack: up and down with the data
intact, a failing migration, `down all` and back, the baseline, the refusals, and the ssh path
to production through a stand-in. CI runs it after the smoke test.

### The baseline

`0001-baseline` is the schema as it was when the migrations began: 45 tables and 286 columns,
as production had them on 2026-10-08, when it recorded the baseline. The file is the
`mysqldump --no-data` of 2026-01-04 the tests used to load, completed with the six `urlname`
columns that dump lacked and production has. It runs only on an empty database. A database
that already has that schema records it instead:

```bash
make migrate-baseline        # the local database
make ovh-migrate-baseline    # production, once; see deploy/README.md
```

`baseline` compares every column the baseline would create with what the database has, refuses
if any is missing (an empty database takes `make migrate`), and notes columns the database has
that no migration describes. Until a database has a record, `make migrate` refuses to run on it
when it has tables: the baseline would otherwise recreate them.

### Production

Production's database is migrated from the Mac, over ssh into `hhbd-db-1` on the OVH host, with
`make ovh-migrate` before the release that needs the change; `make ovh-migrate-down` asks for a
typed confirmation. [deploy/README.md](../deploy/README.md) has the order.

## Audit columns

What the columns that record a row's history mean, and how they are kept (#48). The names
predate any convention (`addedby` here, `com_updated_by` there) and stay as they are:
renaming them across 45 tables would risk more than it would clear up.

| Column | Means | Kept by |
|---|---|---|
| `added` | when the row was added | the database: `DEFAULT current_timestamp()`, and **never** `ON UPDATE`, so an update cannot rewrite it |
| `addedby` | who added it: an `ID` in the old `users` table; `0` means unknown | whoever inserts; the archived backoffice did |
| `updated` | when the row was last **edited**, by a person | whoever edits; nothing automatic |
| `updatedby` | who edited it, an `ID` in `users` | whoever edits |
| `status` | `999` published, counted by the site; `0` not published (the only two values production holds) | the editor |
| `viewed` | page views | the application, on every view (`/stat`) |

The catalog tables (`albums`, `artists`, `songs`, `labels`, `news`) have no automatic
`updated` on purpose: the application bumps `viewed` on every page view, and an `ON UPDATE`
would turn "last edited" into "last viewed". `make test-schema` holds all of this to account,
and CI runs it.

For migrations that follow from it:

- **A data fix leaves `updated` and `updatedby` alone.** Repairing encodings or links (#24, #27)
  is not an edit by a person; which migration changed what is recorded in `schema_migrations`.
- **A new table records its own history**: `added datetime NOT NULL DEFAULT current_timestamp()`,
  and an `updated` that only its writers set. `datetime`, like the catalog's own `added`: a
  `timestamp` cannot hold a time after 2038-01-19. A log has its own name for it, as
  `import_runs.started` does.
- **No column defaults to a zero date** (`'0000-00-00 …'`): strict SQL modes reject it.

One thing is known lost and cannot be recovered from the database: 58 of the 120 `added`
values in `artists_photos`, overwritten in one mass update while `added` still had `ON UPDATE`.

## External ids

`external_ids` (0006, #51) holds the ids a catalog row has in other databases: Discogs,
MusicBrainz, Wikidata and the rest. An import looks a row up by these before anything else, so
a batch that runs twice finds the rows it created the first time instead of adding them again.

| Column | Holds |
|---|---|
| `entity_type`, `entity_id` | the row: `album`, `artist`, `label` or `song`, and its `id` |
| `source`, `kind`, `value` | the id, written `source:kind:value` in a batch: `discogs:master:1234567` |
| `added` | when it was recorded |

A row may carry any number of ids, since sources split, merge and add them over time; **an id
belongs to at most one row**, which the primary key `(source, kind, value)` enforces. A second
row claiming the same id is a duplicate to look at, not an id to move, so
`Model_ExternalId_Api::add()` refuses it with `Model_ExternalId_ConflictException`. There is no
foreign key: one column cannot reference four tables. Deleting a catalog row leaves its ids
behind; whoever deletes one deletes them too.

The vocabulary is closed. Anything outside it is refused, so a typo in a batch cannot invent a
source nobody looks up:

| `source` | `kind` | `value`, as stored |
|---|---|---|
| `discogs` | `master`, `release`, `artist`, `label` | a positive number |
| `musicbrainz` | `release_group`, `release`, `recording`, `artist`, `label` | a UUID, lower case |
| `wikidata` | `item` | `Q` and a number, upper case |
| `deezer` | `album`, `artist` | a positive number |
| `itunes` | `collection`, `artist` | a positive number |
| `plwiki` | `pageid` | a positive number: the page id, which survives a rename the title does not |
| `barcode` | `gtin14` | 14 digits |
| `isrc` | `isrc` | 12 characters, upper case, no dashes |

**Values are normalised before they are stored or looked up**, by
`Model_ExternalId_Api::normalise()`, so one id written two ways is one value. Barcodes become
GTIN-14: digits only, left-padded with zeros, so the EAN-13 `0 190295 868383` a Discogs release
shows and the UPC-A `190295868383` MusicBrainz has for the same record are both
`00190295868383`. Write through the model rather than with a bare `INSERT`; the model also binds
every value instead of putting it into the query.

The columns compare bytes (`utf8mb4_bin`): an id matches exactly or not at all, whatever
collation the catalog tables move to (#71).

Every id a row has:

```sql
SELECT source, kind, value, added FROM external_ids
 WHERE entity_type = 'album' AND entity_id = 535 ORDER BY source, kind, value;
```

## Import runs and provenance

Two tables (0007, #52) record what each import did and where every field it set came from.
Sources differ in licence (Discogs data is CC0, a Commons photo has its own licence, covers are
kept as tolerated use with a takedown path), so "where from" decides what may be shown and how.
It also answers a licence question or a takedown per row and per file, and lets a later batch
tell the fields it set from those a person edited since.

**`import_runs`**: one row per batch file the importer reads, dry runs included.

| Column | Holds |
|---|---|
| `batch`, `batch_sha256` | the file's name and its SHA-256, so the listing shows the same file run twice |
| `mode` | `dry-run` or `apply` |
| `started`, `finished` | when it ran; `finished` stays NULL when a crash or a refusal stopped it |
| `created_count` … `refused_count` | the report's totals: created, updated, unchanged, skipped, refused |
| `report` | the whole report, which the database checks is JSON |

**`import_provenance`**: one row per field, per source that supplied it. A field two sources
agree on has two rows.

| Column | Holds |
|---|---|
| `entity_type`, `entity_id` | the row: `album`, `artist`, `label`, `song` or `image`, and its id |
| `field` | a column name, or a part with no column of its own: `cover`, `photo`, `tracklist` |
| `source` | where it came from, from the list below |
| `source_ref` | what was read, inside the source: an id, `discogs:master:1234567`, or a URL |
| `licence` | `CC0`, `CC BY-SA 4.0`, `tolerated`, …; NULL for a bare fact |
| `fetched` | when the source was read, in UTC |
| `run_id` | the run that last wrote the row; a foreign key, so it always names a real run |

The sources are `bandcamp`, `bn` (the National Library, data.bn.org.pl), `commons` (Wikimedia
Commons), `coverartarchive`, `deezer`, `discogs`, `glamrap`, `itunes`, `musicbrainz`, `plwiki`
and `wikidata`. A source that also gives ids has the same name in `external_ids`; a unit test
holds the two lists to that.

Write through `Model_Provenance_Api`: `startRun()`, then `record()` per field, then
`finishRun()` with the totals and the report. It refuses provenance for a dry run, which writes
nothing but its own row, and for a run already finished. Recording a field from the same source
again replaces the reference, licence and time. A page reads a row's provenance with
`getForEntity()` and asks it `cameFrom('cover', 'discogs')`.

Both tables compare bytes (`utf8mb4_bin`), like `external_ids`: what they hold are identifiers,
not text to sort.

```bash
make import-runs         # the last 20 runs on the local database, newest first; N=50 for more
make ovh-import-runs     # the same on production
```

Every field of a row and where it came from:

```sql
SELECT field, source, source_ref, licence, fetched, run_id FROM import_provenance
 WHERE entity_type = 'album' AND entity_id = 535 ORDER BY field, source;
```

## Test fixtures

`database/tests/fixtures.sql` holds deterministic data with the IDs the smoke test expects,
written for the baseline schema.

### Records

| Table | Records | Notes |
|-------|---------|-------|
| artists | 51 | Including Pezet, Eldo, Mes (ID 35), etc. |
| albums | 50 | Including "Jestem Hip Hopem", Superextra (ID 535) |
| songs | 31 | Including Pogoda (ID 7329) with features |
| labels | 15 | Including Alkopoligamia (ID 58), Asfalt |
| news | 6 | Including ID 1877 (Onar article) |
| artists_photos | 30 | Linked to main artists |
| hhb_users | 10 | Test users for ratings/comments |
| ratings | 100 | For Top10 rankings |
| ratings_avg | 50 | Average ratings for albums |
| searches | 15 | Popular search terms |

### Test images

Generated by `app/tools/generate-test-images.php`:

| Type | Count | Directory | Filename Pattern |
|------|-------|-----------|------------------|
| Album covers | 50 | `content/a/` | `test-cover-001.jpg` to `test-cover-050.jpg` |
| Artist photos | 30 | `content/p/` | `test-artist-001.jpg` to `test-artist-030.jpg` |
| Label logos | 15 | `content/l/` | `test-label-001.jpg` to `test-label-015.jpg` |

**Total:** 95 placeholder images (100x100px, ~1.5KB each)

### What the smoke test needs

| Entity | ID | Content |
|--------|-----|---------|
| Artist | 35 | Mes (Piotr  Szmidt) |
| Album | 535 | Superextra (by Wdowa, label Alkopoligamia) |
| Song | 7329 | Pogoda (features Dj Technik, Beatmo) |
| Label | 58 | Alkopoligamia |
| News | 1877 | ONAR article with "Onar wraca z nowym singlem" |

- Homepage: Contains "Pezet"
- Album list: Contains "Jestem Hip Hopem"
- Premieres: Contains "Stasiak"
- Artist list: Contains "Eldo"
- Label list: Contains "Asfalt"
- Search "tede": Returns "Mefistotedes" and "MercTedes"
- Top10 page: 70+ list items, 40+ thumbnails

The data includes Polish characters (ą, ę, ć, ź, ż, ó, ł, ś, ń) in artist names, album titles,
song lyrics, news content and photo descriptions.

### Changing the fixtures

1. Edit `database/tests/fixtures.sql`, keeping the IDs above.
2. Update image references if needed.
3. `make reset-db`, then `make smoke`.

**Never** load the fixtures into production. Production's data moves through migrations only.
