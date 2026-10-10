# The import contract

How the content project (`hhbd-content`) hands releases, artists, labels and images to hhbd
(#56). The contract is a JSON Schema,
[`app/docs/import.schema.json`](../app/docs/import.schema.json): hhbd-content validates its
batches against the schema at a commit of this repository, and the importer validates every
document against the same file (`Jkl_JsonSchema`) before it writes anything. This page explains
what the schema cannot say: what the fields mean, how a document finds its row, and what gets
written where.

## Running it

The importer is a job, `importer` in the compose files under the `jobs` profile, in its own
image: the one with GD (#96). It takes a batch as a tar on stdin and prints each document's line
on stderr as it reads it and the report, JSON, on stdout.

```bash
make import BATCH=tests/import/batch                  # a dry run on the local stack
make import BATCH=tests/import/batch MODE=apply       # the rows, and the images into content/
make ovh-import BATCH=/path/to/batch                  # a dry run on production, over ssh
make ovh-import BATCH=/path/to/batch MODE=apply
make import-runs                                      # the runs, newest first (ovh-import-runs)
```

A **dry run** reads the whole batch in one transaction and rolls it back, so it reports exactly
what an apply would do, references between documents included, and leaves nothing but its run in
`import_runs`. It writes the images to a temporary directory and deletes them, so a file that
cannot be decoded shows up there too. An **apply** writes each document in a transaction of its
own: a document that fails leaves nothing of itself, and the documents after it still go in.

`tests/import/batch` is a small batch of made-up rows and generated images, which
`tests/import-test.sh` reads on every pull request.

## A batch

A batch is a directory: one NDJSON file, one document per line, and the image files it names
under `files/`. Documents come in this order, so that every reference points at something
already read: labels, artists, releases, standalone images. The same batch read twice changes
nothing the second time.

Every document has a `kind` (`label`, `artist`, `release` or `image`), and all but images a
`ref` of their own (`label:asfalt-records`, `artist:taco-hemingway`, `release:<key>`) that later
documents point at. A document may carry:

- `external_ids`: every id the row has elsewhere, as `{"source:kind": "value"}`, from the
  vocabulary in [database/README.md](../database/README.md#external-ids) (`discogs:master`,
  `musicbrainz:recording`, `wikidata:item`, `barcode:gtin14`, ...). Values are normalised before
  they are compared: a barcode written as an EAN-13 or a UPC-A is one GTIN-14.
- `hhbd_id`: the row's id in hhbd, when the sender knows it.
- `provenance`: where each group of fields came from, `{source, source_ref, fetched_at,
  licence}`, for the groups below. A Discogs field taken from the monthly dump says
  `"licence": "CC0"`; any other Discogs field counts as taken through the API, and the page
  credits it (#62).

| Group | Fields |
|---|---|
| `core` | names and titles, `type`, `real_name`; a release's type, date, label, formats, catalogue numbers, legal flag, parent and credited artists |
| `facts` | `aliases`, `members`, `cities`, `active_since`, `website`, `profile`; a release's `description` |
| `tracklist` | a release's tracks, their songs and credits |
| `cover`, `photos`, `logo` | the images |

## References

A reference (`artists[].ref`, `label.ref`, `members[].ref`, `credits[].ref`, `target.ref`) is
one of:

| Form | Points at |
|---|---|
| `artist:taco-hemingway` | the document with that `ref` earlier in the batch |
| `hhbd:artist:2241` | the row hhbd has with that id (`artist`, `label`, `album`, `song`) |
| `discogs:artist:4320863` | the row that has that external id |
| `name:Taco Hemingway` | the artist or label of that name, when one has it |

A reference to a document that was refused is refused in turn. So is a `name:` that two artists
share (#102): the error lists their `hhbd:artist:` ids to use instead.

## Finding a document's row

A document is matched to a row by its external ids first, its `hhbd_id` second and its natural
key third, and only a document that matches nothing makes a new row:

- a label by its name, an artist by its name, compared under the catalogue's collation
  (`utf8mb4_polish_ci`, so "Żabson" is not "Zabson" but "żabson" is "Żabson", #71);
- a release by its first main artist, its title and its year give or take one.

Two external ids that point at two different rows are a conflict: the document is refused, and
no id is moved (#51).

## Artists who share a name

hhbd can hold two artists of one name, told apart by a qualifier: "Solar (SBM Label)" and
"Solar (raper z Poznania)" (#102, [database/README.md](../database/README.md#artists-who-share-a-name)).
An artist document says which one it means:

- **`disambiguation`.** The importer matches the artist by its name and this qualifier
  together, never by the name alone. When nothing has both, it creates the artist under them.
  External ids and `hhbd_id` still match first, and a row found that way gets the qualifier
  where it has none.
- **`review`**, `{"reason": "same name as hhbd artist 2311", "suggestions": [2311]}`. This asks a
  person to look at the artist and needs a `disambiguation`. It opens a review item; see below.
- **A document with neither** is matched by its name as before. When the name belongs to more
  than one artist, the document is refused, and the error lists them. It needs an id or a
  qualifier.

## What a person settles

A document can say what the batch could not settle on its own. The importer opens a review item
for it (#103), and the report warns of each one. An admin sees the open items on the page of
the row they are about, and all of them under "Do przejrzenia". Reading the same batch again
opens no second item.

| From | Reason | The admin |
|---|---|---|
| an artist's `review` | `namesake` | merges it into a suggested artist, keeps it apart, or changes its qualifier |
| a cover with `needs_upgrade` | `cover_placeholder` | keeps it; a larger cover in a later batch replaces it and closes the item |
| a release's `review`, `{"reason": "date_disputed", "values": ["2013", "2013-05-17"]}` | `date_disputed` | picks one of the values, or types another |
| a release's `review`, `{"reason": "type_disputed", "values": ["single", "ep"]}` | `type_disputed` | picks one, or another type |
| a release's `review`, `{"reason": "single_source", "note": "..."}` | `single_source` | marks it checked |
| an album the import made that lacks a part (below) | `incomplete` | publishes it as it is ("Opublikuj mimo braków"); a later batch that brings the rest publishes it and closes the item |

## What is published

An album the import makes is shown on hhbd.pl only when it has all three (#168):

1. a label, or it is a self-release: `self_released: true`, "wydanie własne", a nielegal;
2. a release date to the day: `release_date_precision` `day`;
3. a tracklist, at least one track; their lengths are not needed.

Any other album is still added, unpublished (`albums.status` 0): a visitor finds it nowhere and
its page answers 404, and an admin sees it with a review item saying what it lacks, as "brak:
wytwórnia, data dzienna, tracklista". The report warns of it like any other item.

A later batch that brings the rest publishes it and closes the item; one that brings part of it
rewrites the item to what is still missing. A batch that finds an album the import made
published but short of a part, as the albums of run 14 were, holds it back the same way. An
album hhbd had before any import keeps its status whatever it lacks, and so does one an admin
published as it was: no batch takes those back.

## A row hhbd has

A matched row is **filled, never overwritten**. A field the batch has and the row leaves empty is
written; a field the row holds with another value stays as it is, and the report warns of it for
a person to decide. The same goes for:

- an artist's type: `x`, the type nobody chose, takes the batch's; any other stays;
- external ids: the row gets the ones it lacks, but of a kind a row has one of (a Discogs
  master or artist, a MusicBrainz release group, artist or recording, a Wikidata item, ...)
  a second value is a warning, and hhbd keeps its own;
- a tracklist: an album with tracks keeps them, and a batch with another number of tracks is a
  warning;
- a cover or a logo: an album or label that has one keeps it, unless every cover it shows is
  a stand-in (`needs_upgrade`) and the batch's is larger: that one takes its place (#103);
- photos: one the artist has (the same original) is not added again, and an artist gets five
  at most (#96);
- a release date: a more precise one inside the month or the year hhbd has, a day in that
  month, replaces it with its precision, as publishing needs the day (#168); one that disagrees
  is a warning;
- a self-release: fills in only where the album has no label; with one, it is a warning.

Links (aliases, members, cities, credits) are added where missing; none is removed.

## Fields and where they go

| Document | Field | Goes to |
|---|---|---|
| label | `name`, `website`, `profile` | `labels` |
| label | `logo` | `content/l/<sha256>.png`, 300 px on the longer side, `labels.logo` |
| artist | `name`, `disambiguation`, `type`, `real_name`, `active_since`, `website`, `profile` | `artists` |
| artist | `review` | `review_items`, reason `namesake` (#103) |
| release | `review` | `review_items`, its `reason` (#103) |
| artist | `aliases` | `altnames_lookup` |
| artist | `members` | `band_lookup`, and the artist's type becomes `b` (#65) |
| artist | `cities` | `artist_city_lookup`, a city made when it is new (#64) |
| artist | `photos` | `content/p/<sha256>.jpg`, 600 px, `artists_photos` with credit and licence (#61) |
| release | `title`, `release_type`, `legal`, `parent_release` | `albums` (#53) |
| release | `release_date`, `release_date_precision`, `announced` | `albums.year` as a whole date, its precision, `announced` (#54) |
| release | `artists` | `album_artist_lookup` with role, position and credited name (#58) |
| release | `label` | `albums.labelid`; `null` for a release without a label (#55) |
| release | `self_released` | `albums.self_released`, "wydanie własne" on the page; the schema refuses it with a `label` (#168) |
| release | `formats`, `catalog_numbers` | `albums.media_*`, `albums.catalog_*` (#53) |
| release | `cover` | `content/a/<variant>/<sha256>.jpg`, `album_covers`, `needs_upgrade` as sent (#60) |
| release | `tracklist` | `songs` (one reused when its `musicbrainz:recording` or ISRC matches), `album_lookup` with disc and position (#59), credits in `artist_lookup`, `feature_lookup`, `music_lookup`, `scratch_lookup`, `remix_lookup`, roles by name (#66) |
| image | `target`, `role`, `file` | as for a cover, photo or logo above, for a row hhbd already has |

Every row the import adds says `addedby = 1100`, every field it changes `updatedby = 1100` (#63).
Every group of fields a document changed gets an `import_provenance` row, and only those: a
group hhbd kept its own values for is not credited to the batch. Every run gets an
`import_runs` row with its totals and its report (#52).

## Files

A batch ships one original per cover, photo or logo, under `files/`, with its width, height,
SHA-256 and MIME type (JPEG, PNG or WebP). The importer reads the header and hashes the content
and refuses the document if they disagree, or if the file cannot be decoded. From the original
it writes, re-encoded without metadata (JPEG at quality 90, a logo as PNG to keep its
transparency), never upscaled (#96):

- a cover's `orig` (1500 px on the longer side at most, and only from an original larger than
  600), `600`, `300` and `75`;
- a photo at 600, marked `modified` when it was scaled down, as a CC licence asks to say;
- a logo at 300.

Every file is named after the original's SHA-256, so the same image in a later batch is found
again. The files are written under a staging name and take their real one only after the
document's rows are committed; a document that fails leaves none.

## The report

```json
{
  "run": 12,
  "mode": "apply",
  "batch": "batch.ndjson",
  "totals": {"created": 6, "updated": 3, "unchanged": 1, "skipped": 0, "refused": 0},
  "documents": [
    {"line": 1, "kind": "label", "ref": "label:...", "action": "created", "entity": "label",
     "hhbd_id": 73, "url": "/...-l73.html", "warnings": [], "errors": []}
  ]
}
```

`action` is `created`, `updated` (a row hhbd had, changed), `unchanged` or `refused`, with the
reasons in `errors`. `url` is the row's page after an apply. The job exits 0 when every document
was read, 1 when any was refused, 2 when the batch could not be read at all; `make` turns any
failure into its own exit code 2.
