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
