# Changelog

Releases use CalVer, `YYYY.MM.N`: the year, the month, and a counter that starts at 0 each
month (`2026.10.0`, `2026.10.1`, `2026.11.0`). A release is the git tag `vYYYY.MM.N`,
published as a release — `gh release create vYYYY.MM.N` — which is what deploys it
(`.github/workflows/deploy.yml`); a tag pushed on its own deploys nothing. Releasing is a
person's decision; nothing releases on its own.

## Unreleased

### Added
- A logged-in admin sees who added an album, artist, song or label and when, as one more line
  of the page's details: "Dodano: 12 maja 2009, 14:03 (Kuba)", the import and an unknown author
  by those names. Nobody else sees it.
- The addresses hhbd had before the `.html` ones lead to today's pages (#26), as old profiles,
  news and other sites still link them: `/n/peja`, `/a/…`, `/l/…`, `/s/…` and the later
  `/wykonawca/…`, `/album/…`, `/wytwornia/…` by the slug kept in `urlname`, `/news/223` by its
  id. A slug that names one row answers 301 to its page; one that names none or several, 302 to
  the search for its words. Of the 497 slug links in production's texts, 473 find their page.
  The texts keep their old links (#24): the redirects lead them, and other sites' links to the
  old addresses, where they belong.

### Changed
- nginx and the app run in two colours, `nginx-blue`/`app-blue` and `nginx-green`/`app-green`
  (#124). A deploy starts the new release beside the one serving, and the shared edge moves to
  it once its home page renders, so no request waits on a release. Until now nginx and the app
  were recreated in place, and every request that arrived meanwhile waited about seven seconds
  while nginx waited for the new app.
  - Each colour has a network of its own, on which only its app answers to `app`, so nginx's
    `fastcgi_pass app:9000` is unchanged.
  - The database and the importer run once.
  - The backfills run in a one-off `app` under the `jobs` profile, on the live tag.
  - `make ovh-e2e` runs both colours and drives blue.
  - `scripts/check-images.sh` reads production's files from whichever container mounts the
    content volume.
- The importer's image is Debian's own PHP 7.4 with GD from packages, where the official image
  compiled GD on every build that missed the layer cache: the same PHP 7.4.33, JPEG, PNG and
  WebP, 349 MB instead of 747 MB, and nothing to compile. The builder stage compiles no GD
  either; CI makes its test images in the importer's image, `make test-images`.
- `make reset-db` keeps the result of a build from the migrations and loads it on the next
  reset, in under a second, until a migration, a fixture, one of its scripts or the day changes;
  `RESET_DB_FULL=1` always builds. `make test-reset-db` checks the two give the same database,
  checksum for checksum.
- CI: the smoke workflow runs in two halves at once, the site's pages and the tools that change
  the database; the reset and migration tests no longer run the smoke test again, and count
  rows in one query rather than one per table; each image keeps its layer cache under its own
  scope, where the shared one meant the app's image was built from scratch on every run; and
  the production stack test gets its images built with the layer cache.

### Fixed
- Polish letters stored mangled come back (#27, migration 0031): UTF-8 read as cp1250, cp1252 or
  latin-1 and stored again, once or several times over (`JÄ¹ąW` for `JŹW`, `PÃ“Ä¹ąNIEJ` for
  `PÓŹNIEJ`), and old song titles in ISO-8859-2 read as latin-1 (`W³a¶nie` for `Właśnie`). The
  migration holds no value from the database, only the sequences, letters and row keys a scan of
  production found; it keeps no copy of the old values and its down undoes nothing.
- A request for a `.php` file that does not exist, `/wp-login.php` and the like from bots, gets
  nginx's own 404 without reaching PHP-FPM. Each one used to write an `[error]` line, "Primary
  script unknown", that showed on the error panels though nothing was wrong (#34).
- The downs of 0003 and 0004 change the `added` defaults with `MODIFY`. Their `ALTER COLUMN
  ... SET DEFAULT` did nothing, with no error, on a database loaded from a dump, as a restore of
  the nightly backup is: MariaDB 10.11 ignores it when the `current_timestamp()` default came
  with `CREATE TABLE`. `make reset-db`'s kept result showed it.
- nginx passes a response larger than its buffers, such as `/sitemap-songs.xml`, straight on
  instead of spooling it to a temporary file. That spooling wrote a `warn` line in nginx's own
  format on every such request, the only line nginx wrote on production after 2026.10.8 (#101).

## 2026.10.8 — 2026-10-09

hhbd logs in the shared host's format (#101): every line of the app container is JSON, nginx
keeps no access log of its own, and a start says nothing. A merge through `make ovh-edit`
keeps the kept album's cover. No migration.

### Fixed
- Merging an album into another that has a cover keeps that cover on the page. The merged
  album's cover rows come along as not main, where before they would have been taken as the
  newer cover. #114's dry run showed it, so album 850 was deleted rather than merged.

### Changed
- hhbd logs in the shared host's format (#101; CONTRACT.md §9 in gcloud-ovh-migrate): one JSON
  object per line with `time` in UTC, `level`, `msg`, `logger`, and the edge's `request_id` on
  a line written for a request.
  - Zend_Log writes through `Jkl_Log_Formatter_Json`, with no new dependency.
  - PHP's errors, uncaught exceptions and fatal errors come through handlers that
    `auto_prepend_file` installs in every script. Each is one line with `error` and `stack`, cut
    to stay under 16 KB, and PHP-FPM's `log_limit` lets it through whole.
  - A page that does not exist is an `info` line. Only a failed request is an `error`, where
    every exception used to be EMERG.
  - nginx keeps no access log, which the edge already keeps, and sets `log_not_found off`.
  - The nginx entrypoint and PHP-FPM say nothing at a start.
  - The importer's progress lines are JSON too.
  - `nginx` and `app` leave `LOG_FORMAT_PENDING`, so `make ovh-stack-test` checks every line
    they write.
  - `make ovh-e2e` reads the client's address through the application instead of the access
    log.

## 2026.10.7 — 2026-10-09

The command line for an admin's edits, with its journal and undo (#115), whose tables migration
0030 put on production on 2026-10-09; the first edit through it removes the second copy of
"Trzy" (#114). Migration 0029, already on production, no longer names the image files lost with
the 2014 snapshot (#47).

### Added
- An admin edits the catalogue from the command line, with a journal of every change and a way
  back (#115).
  - `make edit` locally and `make ovh-edit` on production.
  - The operations: merge two albums or two artists; delete an album, an artist or a label; set
    one field.
  - Every call needs `BY`, an hhbd admin, and `WHY`.
  - A call is a dry run unless `MODE=apply`.
- The journal and the way back.
  - Migration 0030 adds `edit_operations` and `edit_journal`: one row per call, and one per row
    changed, with the row before and after.
  - `DO="undo <operation>"` puts back every row an operation deleted, moved or changed.
  - It also adds `album_merges`, so a merged album's page answers 301 with the album kept, as a
    merged artist's already did.
  - The review panel's namesake merge is the same operation and is journalled the same way.
  - `tests/edit-test.sh` runs every operation and its undo, and compares the data before and
    after.

### Fixed
- The catalogue no longer names the 89 image files production has lacked since its content was
  restored from a 2014 snapshot (#47): 87 album covers, one artist photo and one label logo.
  - They are in no backup, archive or old disk.
  - Migration 0029 clears the album covers and the logo, and deletes the photo's row. It
    archives every value, so its down puts them back.
  - The pages show the placeholder, as they do for an album that never had a cover, and
    `make ovh-check-images` is clean.
  - The covers come back through the import: an album with no `album_covers` row takes the
    cover a batch brings. The one of them that kept a thumbnail (576) has it marked
    `needs_upgrade`, so a larger cover replaces it.

## 2026.10.6 — 2026-10-09

The first release through the shared host's template: the `Deploy` workflow builds the three
images and rolls the app and nginx out with the template's release script, and keeps them only
once the smoke test a release has always had to pass does. Published from `250c63c`, it also
brings the code for migrations 0024 to 0028, which production's database has run since
2026-10-09: no zero dates (#88) and the pages asking the database for less (#69). The server's
`NO_ZERO_IN_DATE,NO_ZERO_DATE` comes with the compose file `make ovh-install` puts on the host
and a `make ovh-db-up`.

### Changed
- Production's stack is laid out as the shared OVH host's service template has every service do
  it (#108). Production runs as before: the same files go to the same places on the host.
  - The stack, the edge snippet and the secrets live in `deploy/ovh/`, with a `service.env`
    naming the service and its three images. The install, release and stack-test scripts,
    `deploy/ovh/ovh.mk`, the secrets scripts and the deploy workflow are the template's, word
    for word, so a change to them there reaches every service on the host.
  - A release is `gh release create vYYYY.MM.N`. The `Deploy` workflow builds the app, nginx
    and importer images and rolls them out with `scripts/ovh-release.sh`, which runs
    `deploy/ovh/smoke.sh` and puts the release before back when it fails. A tag pushed on its
    own deploys nothing now; the `Release` workflow, `deploy/ovh-release.sh` and its test are
    gone, the release flow being the template's to test.
  - `make ovh-stack-test` checks the stack against the host's contract and serves it behind the
    edge's real configuration; `deploy/ovh/stack-test-setup.sh` migrates and seeds its database
    first. It reads past the log lines of `nginx` and `app` until #101.
  - This repository's own test of the stack is `make ovh-e2e` (was `make test-ovh-stack`), and
    the import script is `deploy/ovh/import.sh`.
  - The secrets targets are `make ovh-secrets-set`, `-show`, `-edit`, `-check` and `-init`
    (were `make secrets-*`).
  - The host's address is read from gcloud-ovh-migrate's `.env`; `OVH_HOST` and `OVH_SSH_USER`
    left `.env.example`.
  - `make ovh-smoke` runs the release's smoke test against `https://hhbd.pl`. The way around
    Cloudflare it took before the DNS moved is closed at the edge.
- No column holds a zero date any more, and MariaDB runs with `NO_ZERO_IN_DATE,NO_ZERO_DATE`
  (#88).
  - Migrations 0024 to 0027 clear what production held on 2026-10-09: artists' start and end
    dates (787 and 795), band members' join and departure dates (585 each), users' added,
    updated and last-login times (24, 1 844 and 369), and 108 news expiry times.
  - What was not known becomes NULL. The 28 partial dates (1998-00-00, 1998-03-00) become whole
    dates with a precision, as release dates did.
  - Each migration archives what it changed, and its down restores it.
  - A CHECK on each column refuses a zero part.
  - The three compose files run the server with the two flags.
  - The fixtures and the migrations, which have to read and write the old zero dates, do so in
    a session of their own.

- The pages ask the database for less (#69). Measured over the same 47 pages on a copy of
  production:
  - temporary tables sent to disk: 1 901 of 2 038 before, 1 after;
  - rows read by full scans: 1.02 million before, 374 thousand after;
  - full joins: 49 before, 20 after.

  The changes behind it:
  - Migration 0028 adds the indexes the joins lacked: the link tables by artist,
    `album_lookup` by album, `albums` by label, `songs` by views.
  - The album lists read `albums` alone, with the credits fetched as before, instead of
    grouping a join of albums, artists and labels whose `SELECT *` carried TEXT columns into a
    temporary table on disk.
  - A song's credits read the three columns a credit shows.
  - The most viewed songs, and an artist's albums and songs, are sorted as ids first and read
    by id.
  - The popular searches and the label list group a VARCHAR, not a TEXT.

### Fixed
- Registering an account works again. `hhb_users.usr_updated` and `usr_last_login` were
  NOT NULL without a default, and a registration writes neither, so the server's
  `STRICT_TRANS_TABLES` refused it. Migration 0026 makes them nullable.

## 2026.10.5 — 2026-10-09

The code for migration 0023, which production's database has run since 2026-10-09: an admin
settles on hhbd.pl what an import left for a person, and the importer opens those items. With
this release a batch may carry a release's `review`, and a larger cover replaces a stand-in.

### Added
- An admin settles on hhbd.pl what an import left for a person (#103).
  - Migration 0023 adds `review_items` and `artist_merges`.
  - The importer opens an item for:
    - an artist's `review` (a namesake, #102);
    - a stand-in cover;
    - a release's new `review`: a date or a type the sources disagree on, or a release only one
      catalogue knew.
  - A logged-in admin sees the open items on the album, artist or label page, and all of them
    with counts under "Do przejrzenia". A visitor sees nothing, and those pages are not cached.
  - Each item is settled with one form, carrying a session token: merge a namesake into an
    artist, keep it apart or change its qualifier; keep a cover; pick a date or a type; mark a
    release checked.
  - A merge moves every reference to the artist kept, deletes the duplicate, redirects its old
    URL, and records what it moved so it can be undone.
  - A larger cover in a later batch replaces a stand-in and settles its item. That is the way to
    upload a better cover, so the web image stays without GD.
  - `tests/review-test.sh` runs every action as the fixtures' admin.

### Fixed
- The fixtures' admin can log in: the password is hashed with the salt `Model_User` adds.

## 2026.10.4 — 2026-10-09

The code for migration 0022, which production's database has run under 2026.10.3 since
2026-10-09: two artists may share a name, told apart by a qualifier, and an import can bring
such a namesake. With this release a batch may carry `disambiguation` and `review`.

### Added
- Two artists may share a name, told apart by a qualifier (#102). The content project meets such
  names every year it reads, 71 in 2016 alone.
  - Migration 0022 adds `artists.disambiguation`. The unique key moves from the name to the name
    and qualifier together, and a name without a qualifier still belongs to one artist.
  - The artist's page, title, Open Graph and slug carry the qualifier: "Solar (SBM Label)" at
    `solar-sbm-label-p64.html`. A list carries it only when it holds both artists: the artist
    lists, the search and an album's credits.
  - The search keeps two artists of one name apart; it used to merge results by name.
  - The importer matches an artist document with a `disambiguation` by name and qualifier, never
    by the name alone, and creates the artist under both. A document or a `name:` reference
    whose name two artists share is refused, with their ids. A `review` is a warning in the
    report until #103.
  - The schema gains both fields.
  - The fixtures hold two Solars and a band one of them is in.
  - The down folds the qualifier into the name, and refuses before changing anything when that
    name is taken.

### Fixed
- `make ovh-covers-backfill` and `make ovh-photos-backfill` mount production's content volume.
  They named it `content`, as the compose file does, but `docker compose run -v` takes Docker's
  name, `hhbd_content`, and made a new, empty volume instead, so the backfill found no files.
  They now name `hhbd_content` and check it exists before the run.
- A backfill's dry run reports what the run would do. It counted every file as new, rows
  already there and duplicate photos included; it now writes in a transaction it rolls back.
  `DRY_RUN=1` asks for one from `make`, and `tests/backfill-test.sh` checks both backfills in CI.

## 2026.10.3 — 2026-10-09

The code for migrations 0016 to 0021, which production's database has run under 2026.10.2
since 2026-10-09, and the importer. Once it is deployed, `make ovh-covers-backfill` and
`make ovh-photos-backfill` describe the covers and photos already on the content volume, and
`make ovh-import` can read the first batch.

### Added
- The importer (#56): `make import` and `make ovh-import` read a batch from the content project
  into the catalogue, as `docs/import.md` describes. Each document is validated against the
  contract, matched to a row by external ids, hhbd id and natural key, and written in a
  transaction of its own; a row hhbd has is filled where empty and never overwritten, with a
  warning for each value it keeps. The same batch read twice changes nothing; a dry run
  reports what an apply would do and leaves nothing behind. The report goes to stdout and into
  `import_runs`, and every group of fields it changed into `import_provenance`.
- The importer writes the cover, photo and logo sizes from the one original a batch ships
  (#96): JPEG, PNG or WebP in, checked against its declared size and hash, scaled down, never
  up, and moved into `content/` only after its rows are in. An artist gets five photos at most.
- A third image, `ghcr.io/jkulak/hhbd-importer`, built with every release for the `importer`
  job (`profiles: [jobs]`, so no deploy starts it). It is PHP's CLI image with GD for JPEG, PNG
  and WebP: a new dependency, the PHP extension built from PHP's own sources, approved for this
  image on #96; the web image stays without it.
- `tests/import-test.sh` reads a test batch of made-up rows and generated images on every pull
  request: a dry run, an apply, the same apply again, and a batch with broken documents. The
  production stack test feeds the batch to the importer as `make ovh-import` does.
- An artist page shows all the artist's photos, the main one first and the rest in a gallery,
  each captioned with its author, licence and any change, as CC licences require (#61).
  Migration 0020 makes `artists_photos.artistid` an int and adds width, height, SHA-256, MIME
  type, licence, licence URL, credit and a modified flag, one file once per artist;
  `make photos-backfill` and `make ovh-photos-backfill` record the files' facts, and
  `Model_Image_Api::addArtistPhoto()` keeps one main photo per artist.
- `album_covers` (migration 0019, with a down) describes each cover file: variant, path, size,
  SHA-256, MIME type, source, licence (#60). The album page and the lists read it, with the
  file's width and height on the `<img>`, and fall back to `albums.cover` for an album without
  rows. `make covers-backfill` and `make ovh-covers-backfill` describe the covers already on
  the volume; `make check-images` also verifies each row's file and hash.

### Changed
- The catalogue is stored in `utf8mb4` with the Polish collation, and the application connects
  in `utf8mb4` (#71). Names with emoji or other four-byte characters store, and an import
  matching by name tells "Żabson" from "Zabson" while ignoring case; lists sort in Polish order.
  Migration 0021 converts the 44 tables still in `utf8mb3`, in a second on a copy of
  production, where all 2 256 artist names stayed distinct and the down restored every table's
  definition and checksum.
- Release dates are stored whole with their precision instead of zero parts, and an album says
  whether it is announced (#54). Migration 0018 rewrote 154 dates, gave the two 0000-00-00 no
  date, marked 8 guessed announcements never filled in, removed the four 2017 placeholders
  that topped the album list for years, and adds a CHECK against zero parts. The album lists
  and counts read `announced`; the newest list now starts with a real release.
- Albums without a label have `labelid` NULL; the placeholder label 27 "BRAK" and the pages'
  special cases for it are gone (#55). Migration 0017 moved its 247 albums.
- A track's disc has a column of its own instead of `disc * 100 + position` (#59). Migration
  0016 decoded 1 348 rows, put the 6 tracks at position 0 last on their disc and removed the
  22 rows of albums that no longer exist; its down puts every touched album back whole.

## 2026.10.2 — 2026-10-09

What 2026.10.1 was to bring, which its own smoke test kept off production (see Fixed), and since
then the import contract, Discogs credits on the pages, and placeholders for missing images.

### Added
- The import contract (#56): `app/docs/import.schema.json`, a JSON Schema for every document of
  an import batch, which hhbd-content validates its batches against and the importer will
  validate every document with; `docs/import.md` explains the fields, the references and how a
  document finds its row. `Jkl_JsonSchema` validates the subset of JSON Schema the contract
  uses, with no new dependency.
- The album and artist pages show "Data provided by Discogs.", linked to the Discogs page,
  when any of their data came through Discogs's API, and an album page links where it can be
  heard on Deezer and Apple Music when its ids are known; the about page says the site is not
  affiliated with Discogs (#62). Whether a credit is due comes from `import_provenance`: data
  from Discogs's CC0 dump is recorded with that licence and needs none.
- `make reset-db` loads `database/tests/fixtures-latest.sql` after the migrations, for the
  tables they add, which the baseline fixtures cannot fill.

### Fixed
- A release's smoke test checks only what holds on production's data (#97). The checks added
  for the fixtures' own cases (an album on no label, a two-disc album, Discogs provenance and
  the rest) looked for rows production does not have, so 2026.10.1 failed its smoke test and the
  host went back to 2026.10.0. `tests/smoke-test.sh` keeps those checks in a section of their
  own, which `deploy/ovh-release.sh` skips with `SMOKE_TARGET=production`; the release flow's
  test checks it does.
- A cover, thumbnail, photo or logo the catalogue names but the content volume lacks shows a
  placeholder instead of a broken image: nginx answers it with a 404 whose body is a
  placeholder shipped in the image (#47). `make check-images` and `make ovh-check-images`
  list such files; production lacks 87 covers, 86 thumbnails, a photo and a logo. CI runs the
  check on the fixtures, whose generator now also writes the album thumbnails.

## 2026.10.1 — 2026-10-09

The catalog made ready for the import from hhbd-content: external ids, provenance, release
types, several artists per album, and the clean-ups the data needed. It also reads the old and
the new shape of dates, labels and discs, so the migrations that change those can follow it.

Never served: its smoke test failed on production and the host went back to 2026.10.0. Everything
here ships in 2026.10.2.

### Added
- Albums have a release type (album, EP, mixtape, compilation, beat tape, single, other) and a
  digital medium with its catalog number (#53). Migration 0014 adds the columns and types the
  albums `singiel` or `epfor` flagged: 12 singles (one to three tracks) and 60 EPs on
  production. The album page and the album lists show the type and `[nielegal]` next to the
  title, and the page lists the media; the catalog number falls back to the LP's, the
  cassette's or the digital one when there is no CD number.
- `Model_FeatType_Api::resolve()` finds a credit's role by name, whatever its case, and adds a
  role nobody has used yet, so imported credits named "Rap" or "Cuty" land on one row each
  (#66).
- `migration_archive` (migration 0009, with a down): a migration that deletes or changes rows
  keeps them there as JSON, so its down puts them back exactly. The rows stay in the database
  rather than in the migration files, which run on the fixtures as well as on production and sit
  in a public repository.
- The import has its own row in `users` (migration 0008, with a down): `ID` 1100, login
  `import`, no password. The importer writes it into `addedby` for what it creates and into
  `updatedby` for what it changes, so an imported row shows as one without a look at
  `import_provenance` (#63). `Model_Provenance_Api::getImportUserId()` reads `IMPORT_USER_ID`
  (1100 when unset) and refuses when the row is missing; `database/README.md` has the query
  that lists what the import added.
- `import_runs` and `import_provenance` (migration 0007, with a down): one row per import batch
  with its mode, totals and JSON report, and one row per imported field and source with the
  reference, licence and fetch time, so a licence question or a takedown is answered per row
  and a later batch knows which fields it set (#52). `Model_Provenance_Api` writes them,
  refusing provenance for a dry run or a finished run, and a page asks a row's provenance
  `cameFrom('cover', 'discogs')`. `make import-runs` and `make ovh-import-runs` list the last
  runs.
- `external_ids` (migration 0006, with a down): the ids a catalog row has in Discogs,
  MusicBrainz, Wikidata, Deezer, iTunes, the Polish Wikipedia, its barcode and ISRC, so an
  import finds the rows it created before instead of adding them twice (#51). An id belongs to
  at most one row, and the vocabulary is closed. `Model_ExternalId_Api` normalises every value
  before it is stored or looked up (barcodes as GTIN-14, so an EAN-13 and the UPC-A of the
  same record match) and refuses an id another row already has. `database/README.md` has the
  vocabulary and the rules.
- `Jkl_Db::fetchAll()` and `query()` take bound values, so new code passes values from outside
  sources as parameters instead of escaping them into the query.
- Database migrations: plain SQL in `database/migrations/`, an `up` and a `down` each, applied
  in order and recorded in the database by `scripts/migrate.sh`. `make migrate`,
  `make migrate-down`, `make migrate-status` and `make migrate-new` for the local database;
  `make ovh-migrate` and its siblings for production, where `make ovh-migrate-baseline` once
  records the schema production already has. `0001-baseline` is that schema: the dump the tests
  loaded until now, completed with the six `urlname` columns production has and the dump
  lacked, which the first `baseline` on production brought to light. `make migrate` refuses a
  database that has tables but no record, and
  `baseline` checks every column it would create is there. `make test-migrate` exercises the
  runner against the live stack, and CI runs it after the smoke test (#42).
- `make reset-db` drops the local `hhbd` database and builds it again the way production's is:
  the baseline from the migrations, the fixtures onto it, every later migration over that data.
  It takes a few seconds, leaves the containers and the volume alone, and refuses a Docker
  engine that is not local, a project with no running db, and a db container started from
  another directory or from production's compose file. `make test-reset-db` checks it, and CI
  runs that after the smoke test (#38).

### Changed
- An album can be credited to several artists (#58). Migration 0015 adds a role, a position and
  a credited name to each credit. Album lists show such an album once instead of once per
  artist, name it after all its main artists ("Pezet & Eldo"), and the album page links each
  of them; titles, Open Graph and the page's description name them all. List queries group by
  album, so a limit of ten shows ten albums.
- Tracklists read a track's disc from a disc column once it exists, and from the old
  disc * 100 + position encoding until then (#59, first step); an album of several discs is
  numbered 1-01, 2-01 either way. The migration that adds the column can follow this release.
- An album without a label shows in every album list and its page renders, with the label left
  out of its description (#55, first step). Lists joined labels with an inner join, so an
  album with no label vanished from them; the placeholder label "BRAK" (27) now reads as no
  label too, so the 247 albums on it no longer say "wydany przez wytwórnię ," with no name.
- Release dates are shown to the precision they are known, read from a precision column once
  it exists and from the zero parts of the date until then (#54, first step). Nothing on the
  pages changes yet: this release reads both shapes, so the migration that stores dates with a
  precision can follow it.
- `deploy/compose.ovh.yaml` labels what the host's nightly backup takes: the database on `db`,
  dumped with the root password the container already has, and the `content` volume on `nginx`.
- The release job runs in the `production` environment, which keeps the deploy key and admits
  release tags alone, instead of reading it from repository secrets any branch could read.
- MariaDB's caches fit an all-InnoDB database (#67): the MyISAM key cache is 8 MB instead of
  128 MB, since no table uses it, and the Aria page cache, which holds on-disk temporary
  tables, 32 MB instead of 128 MB. The InnoDB buffer pool stays at 96 MB, about three times
  the data. `compose.yaml` and `compose.ci.yaml` run the same flags, and the stack test checks
  both the running values and that the three files agree.
- Every table is InnoDB; 44 of 45 were MyISAM (#45). Migration 0005, with a down, converted a
  copy of production's data in a second with the data identical byte for byte. It brings crash
  recovery, row locks instead of table locks, and a consistent snapshot for the nightly
  `mysqldump --single-transaction`. `make test-schema` holds every table to it.
- The archived admin panels (`admin/`, `xadmin/` on `backoffice-archive`) are abandoned for
  good: not updated, not revived, and nothing has to stay compatible with them. A future
  backoffice gets written from scratch.
- `hhbd.pl` and `www.hhbd.pl` are served from the shared OVH host since 2026-10-08, behind
  Cloudflare in Full (strict), with a Let's Encrypt certificate at the origin and every
  connection that does not come from Cloudflare dropped.
- The docs describe the repo as it is: the backoffice is archived on the branch
  `backoffice-archive` and nothing runs it, the dev stack has four services, CI runs on pull
  requests, and production runs on the OVH host (#36).
- CI sets its database up with `make reset-db`, like a developer does, instead of through
  MariaDB's init scripts; `database/tests/01-schema.sql` became the baseline migration and
  `02-test-fixtures.sql` is `database/tests/fixtures.sql` (#42).

### Fixed
- The sitemaps answer again: every `sitemap-*.xml` failed with a parse error, since the
  template's `<?xml` declaration opened PHP under `short_open_tag`. They also give absolute
  URLs, as the protocol requires, and each entry's canonical path; album entries were mangled
  (`/%2Fpezet-…-a1.html-a1.html`) and songs and labels unslugged (`/Intro-s1.html`).
- Role names in `feattypes` are unique, and the unused test role is gone (#66). Migration 0013
  also gave a role to 223 of the 579 credits stored without one, where every other credit of
  the same artist has that role; the rest stay without a role rather than get a guessed one.
  Its down restores both.
- An artist with members is typed as a band (#65). The pages decide "band" by members and show
  no type yet, so nothing visible changes, but the importer and any page that shows the label
  now have one rule to follow. Migration 0012 set type `b` on the 67 artists on production that
  had members and another type; its down restores their types. The 66 bands without members
  are listed on #65 for review.
- Every link table has a unique key, so the database refuses a link stored twice (#57).
  Migration 0011 first removed the copies production held (album_artist_lookup 7, band_lookup
  8, artist_lookup 1, music_lookup 3, collection 3, ratings 2), keeping the published or the
  oldest row, and archived them so its down puts them back. `album_lookup` waits: 162 track
  positions on 19 albums hold different songs.
- Artist pages show the cities the archived backoffice recorded: they were in
  `city_artist_lookup`, which no page read, while the pages read `artist_city_lookup` (#64).
  Migration 0010 moves the old table's pairs over (168 on production, once each; three more
  name a missing artist or city and stay out), so 224 links show instead of 56, drops the old
  table, and adds a unique key on artist and city. Its down restores both tables from the
  archive.
- `added` keeps the time a row was added. In eleven tables it was `ON UPDATE
  current_timestamp()`, so any update rewrote it; `artists_photos` had already lost 58 of 120
  dates that way. The catalog's `added` defaults to the current time, and nothing defaults to a
  zero date any more. The catalog's `updated` stays without an automatic value, since page
  views update those rows. Migrations 0002 to 0004, each with a down; `database/README.md` says
  what every audit column means, and `make test-schema` checks it in CI (#48).

### Removed
- Everything that deployed to Google Cloud: the `env-prod` workflow, `deploy/compose.gcp.yaml`, the
  `deploy/0*.sh` setup and deploy scripts, `deploy/rollback.sh` with its `prod-lkg` tags, and the
  `GCP_SA_KEY` secret. The Google project was deleted on 2026-10-08, the day production moved.
- The one-off data move from Google (`deploy/ovh-data.sh`, its test and `make ovh-data`) and the
  `make gcp-*` targets, which had nothing left to act on.

## 2026.10.0 — 2026-10-08

The first release to the shared OVH host. Production keeps running on Google until the move.

### Added
- Production can run on the shared OVH host, following `CONTRACT.md` in
  `jkulak/gcloud-ovh-migrate` at `5f9a189`: `deploy/compose.ovh.yaml` with nothing published
  and no adminer, the edge snippet `deploy/hhbd.pl.caddyfile`, and `make ovh-install` to put
  both on the host with the secrets.
- Releases from CalVer tags: both images built under one tag, pushed privately to
  `ghcr.io/jkulak/hhbd-{app,nginx}`, deployed through the host's `ci-deploy`, smoke-tested, and
  rolled back to the tag that ran before when the smoke test fails.
- A healthcheck on every container. nginx's renders the home page, so a release that starts
  but cannot serve a page is taken back by the host.
- `content/` lives in a named volume on the OVH host, so the host's backup covers it.
- `deploy/ovh-data.sh` moves the database and `content/` from the Google VM and verifies them:
  exact `count(*)` and a checksum per table, and a sha256 per file.
- Secrets in `deploy/hhbd.enc.env`, SOPS-encrypted to the host and personal keys in
  `.sops.yaml`, with `scripts/secrets-check.sh` as the gate in `make secrets-check` and on
  every pull request.
- The smoke test takes extra curl options from `SMOKE_CURL_OPTS`, so it can reach a host before
  the DNS points at it.
- Tests for the production stack behind a stand-in edge, the data move and the release flow,
  run on every pull request by `deploy-checks.yml`.

### Changed
- PHP, the application and nginx log to the containers' stdout and stderr; nothing writes log
  files inside a container any more. Zend_Log has one writer instead of two files, and PHP-FPM
  no longer logs every request a second time.
- nginx takes the client's address from `X-Real-IP`, trusted only from the edge's fixed address
  172.30.0.2, so comments behind the OVH edge are stored with the poster's address.
- `deploy/05-populate-db.sh` uses the database container's own credentials instead of a
  password written in the script.

- The unit and smoke test workflows run on pull requests only, not again on the push to
  `main` that a merge makes, and a newer push to a pull request cancels the older run.
- `make test-ovh-release` runs the release flow's test.
- `make gcp-ps`, `make gcp-stop-writers` and `make gcp-start` are the only ways this repo
  touches the Google VM besides the data move: look, stop the writers for the final copy, and
  start them again as the way back.

### Fixed
- The PHP image builds again. Its base, Debian 11, is past long-term support, and the regular
  mirrors had stopped serving the libfcgi security fix that their index still listed; the image
  now installs from archive.debian.org.
