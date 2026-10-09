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
- **A migration that deletes or changes rows archives them first**, in `migration_archive`
  (0009): one row per row, with the migration's version, the table, what the up did to it
  (`deleted`, `inserted`, `changed`) and the row as JSON. The down puts them back from there
  and deletes its own archive rows, so it restores exactly what the up removed. The rows stay in
  the database, not in the migration file: the same file runs on the fixtures and on
  production, whose rows differ, and the repository is public while some rows (ratings,
  collections) record what people did. 0010 is a worked example.

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

## Character set and collation

The catalogue is `utf8mb4` with `utf8mb4_polish_ci` (0021, #71): any character fits, "Żabson"
and "Zabson" are two names while "żabson" is "Żabson", and `ORDER BY name` follows the Polish
alphabet. Tables that hold identifiers compare bytes instead (`utf8mb4_bin`: `external_ids`,
`import_runs`, `import_provenance`, `migration_archive`, `album_covers`), and the runner's
`schema_migrations` keeps its own. A new table says which of the two it is. The application
connects with `utf8mb4` too; MariaDB's `utf8` is the three-byte `utf8mb3`.

0021's down restores every column exactly, but refuses once the catalogue holds what the old
`utf8mb3_general_ci` cannot: a four-byte character, or two names kept apart only by a Polish
letter.

## Audit columns

What the columns that record a row's history mean, and how they are kept (#48). The names
predate any convention (`addedby` here, `com_updated_by` there) and stay as they are:
renaming them across 45 tables would risk more than it would clear up.

| Column | Means | Kept by |
|---|---|---|
| `added` | when the row was added | the database: `DEFAULT current_timestamp()`, and **never** `ON UPDATE`, so an update cannot rewrite it |
| `addedby` | who added it: an `ID` in the old `users` table; `0` means unknown, `1100` the import | whoever inserts; the archived backoffice did, the importer does |
| `updated` | when the row was last **edited**, by a person or by the import | whoever edits; nothing automatic |
| `updatedby` | who edited it, an `ID` in `users`; `1100` the import | whoever edits |
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

### The import in addedby and updatedby

The import is a row of its own in `users` (0008, #63): **`ID` 1100**, login `import`, no
password, so nothing logs in as it. The importer writes 1100 into `addedby` for every row it
creates, and into `updatedby`, with the time in `updated`, when it changes a field of a row
that exists; a document it reports `unchanged` touches neither. So an imported row shows as one
in these columns alone; `import_provenance` says from where, field by field.

The importer reads the id from `IMPORT_USER_ID`, 1100 when unset, through
`Model_Provenance_Api::getImportUserId()`, and refuses to run when `users` has no import row
with that id. A fixed id rather than whatever `AUTO_INCREMENT` gives keeps it the same in every
database, so this paragraph can name it.

What the import added, oldest first:

```sql
SELECT 'album' AS type, id, title AS name, added FROM albums WHERE addedby = 1100
UNION ALL SELECT 'artist', id, name, added FROM artists WHERE addedby = 1100
UNION ALL SELECT 'label', id, name, added FROM labels WHERE addedby = 1100
ORDER BY added, type, id;
```

And what it changed that a person had added: the same with `updatedby = 1100 AND addedby <> 1100`.

One thing is known lost and cannot be recovered from the database: 58 of the 120 `added`
values in `artists_photos`, overwritten in one mass update while `added` still had `ON UPDATE`.

## Catalog links

Rules the link tables follow, and the migration that set each one:

- **An artist's cities are in `artist_city_lookup`**, one row per artist and city, which the
  artist page reads (0010, #64). The archived backoffice wrote a second table,
  `city_artist_lookup`, that no page read; 0010 moved its pairs over and dropped it.
- **A link is stored once.** Every link table has a unique key on what one row means (0011,
  #57), so an import that runs twice, or credits a track twice, is refused by the database:

  | Table | Unique key |
  |---|---|
  | `album_artist_lookup` | `albumid, artistid` |
  | `artist_city_lookup` | `artistid, cityid` |
  | `band_lookup` | `artistid, bandid` |
  | `altnames_lookup` | `artistid, altname(190)` |
  | `artist_lookup`, `music_lookup`, `scratch_lookup`, `remix_lookup` | `songid, artistid` |
  | `feature_lookup` | `songid, artistid, feattype` |
  | `city_label_lookup` | `labelid, cityid` |
  | `collection`, `wishlist`, `ratings` | `albumid, userid` |

  `album_lookup` has none yet: even with discs told apart (0016), 180 track positions on 22
  albums hold two or more different songs, mostly two editions' tracklists on one album, which
  a person has to review first (listed on #59).
- **A track's disc is `album_lookup.disc`** and its place on that disc `track`, from 1 (0016,
  #59). The old encoding, `disc * 100 + position` in `track`, is gone, and so is position 0.
- **An artist with members is a band, type `b`** (0012, #65). The pages decide "band" by
  members (`Model_Artist_Container::isBand()`) and show no type today, but `type` is what the
  importer writes and the label a page would show ("Projekt" for `b`), so the two must agree:
  whoever writes `band_lookup` also sets the band's type. A member
  counts when the member's own row exists. A `b` artist without members is not changed: 66 of
  them on production, many of them duos whose members were never entered, are listed on #65.
- **A credit's role is a `feattypes` row, found by name** (0013, #66). Role names are unique
  under the table's collation, so "rap" is "Rap"; `Model_FeatType_Api::resolve()` returns a
  role's id and adds the role when it is new. A credit without a role points at row 0, which
  has no name and is never deleted: the song page joins it for every such credit. 0013 gave
  a role to a role-less credit only where all the artist's other credits share one (223 on
  production); the other 356 stay at 0, because any role for them would be a guess.

## Album credits

An album can be credited to several artists (0015, #58). Each `album_artist_lookup` row has a
`role` (`main` or `featured`), a `position` in the credit line and, when the release names the
artist differently, `credited_as`. The pages list an album once, name it after its main
artists in order ("Pezet & Eldo"), link each of them in the album's heading, and list it on
every credited artist's page. The album's URL and its "other albums" box follow the first main
artist. Credits without positions, as before 0015, are ordered by artist id, the order the
pages always took the first artist in.

## Release types

`albums.release_type` says what a release is (0014, #53): `album`, `ep`, `mixtape`,
`compilation`, `beat_tape`, `single` or `other`. The page shows it next to the title (`[EP]`,
`[mixtape]`, …; nothing for an album), and `[nielegal]` for a release with `legal = 'n'`.
`media_digital` and `catalog_digital` sit next to the CD, LP and cassette columns, so a
digital-only release no longer passes for a CD.

The older columns stay: `singiel` and `epfor` (the album an EP or single preceded, which the
page still links as "Singiel do:"). 0014 typed every album they flag as a `single` when it has
one to three tracks and an `ep` otherwise: 12 singles and 60 EPs on production.

## Album covers

`album_covers` (0019, #60) describes each cover file: the album, the variant (`orig`, `600`,
`300` or `75` px), its `path` under `content/`, width, height, SHA-256 and MIME type, its
`source` and `sourceurl`, its `licence`, whether it is the `main` cover, and whether it
`needs_upgrade`: a stand-in, such as a 600 px Discogs cover, to replace when a larger one turns
up (#96). A path rather than
a bare name, so the covers already on the volume stay where they are (`a/<name>` and
`a/th/<name>-th.jpg`) while new variants go under `a/<variant>/`. One album, variant and hash
appear once.

The album page shows the 600 px cover, else the original, else the 300 px one; lists show the
75 px thumbnail, else the next larger. `<img>` tags carry the file's width and height. An album
without rows keeps the paths `albums.cover` has always given, so the pages look the same before
the table is filled.

The database cannot read files, so `app/tools/covers.php` fills the table from them:

```bash
make covers-backfill        # the local content/, against the local database
make ovh-covers-backfill    # production, in a one-off app container with the volume read-only
make ovh-covers-backfill DRY_RUN=1   # what it would add, nothing written
```

It records each existing cover as `300`, `600` or `orig` by its size and its thumbnail as
`75`, source `legacy`, and adds nothing on a second run. A dry run writes the rows in a
transaction it rolls back, so its report is the run's, rows already there included. `make check-images` also checks every
row's file is there with the recorded hash.

## Artist photos

An artist can have several photos (`artists_photos`); the page shows the main one at the top and
the others in a gallery below, each with the caption its licence asks for: "Fot. <credit>,
<licence>", the credit linked to `sourceurl`, the licence to `licence_url`, and "(zmodyfikowane)"
when `modified` says the file was cropped or resized (0020, #61). `Model_Image_Api::addArtistPhoto()`
keeps exactly one main photo per artist. Width, height, SHA-256 and MIME type come from the
files: `make photos-backfill` and `make ovh-photos-backfill` fill them for the photos already on
the volume (`DRY_RUN=1` to only report), and `make check-images` verifies each recorded hash.
`tests/backfill-test.sh` checks both backfills, their dry runs and a second run, in CI.

## Release dates

`albums.year` is a whole date, or NULL when nothing is known (0018, #54).
`release_date_precision` says how much of it is: `day`, `month` (stored as the month's first
day) or `year` (stored as 1 January). The page shows "18 listopada 2016", "któregoś listopada
2016" or "2016" accordingly. A CHECK refuses zero parts such as 2016-11-00, which the catalog
used before.

`announced` is 1 for a release that is not out yet, or not confirmed out. The album list shows
the albums with 0; the upcoming list shows those with 1 whose date is still ahead. 0018 marked
as announced the dates still to come and eight guessed ones entered years ahead and never
filled in, and removed four 2017 placeholders. An album without a label has `labelid` NULL
(0017, #55); the placeholder label 27 "BRAK" is gone.

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

**Credits follow provenance** (#62). Discogs's API terms ask for "Data provided by Discogs."
with a link next to anything taken through the API; its monthly dump is CC0 and asks for
nothing. So the importer records a field from the dump with `licence = 'CC0'`, and any other
`discogs` row counts as taken through the API: the album and artist pages then show the line,
linked to the row's Discogs page from `external_ids`. The about page carries the notice that
the site is not affiliated with Discogs.

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
written for the baseline schema. Tables that later migrations add get their rows from
`database/tests/fixtures-latest.sql`, which `make reset-db` loads after every migration has
run: an import run with provenance, and external ids.

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
