# Changelog

Releases use CalVer, `YYYY.MM.N`: the year, the month, and a counter that starts at 0 each
month (`2026.10.0`, `2026.10.1`, `2026.11.0`). A release is the git tag `vYYYY.MM.N`, and
pushing it is what deploys it (`.github/workflows/release.yml`). Tagging is a person's
decision; nothing tags on its own.

## Unreleased

### Added
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
