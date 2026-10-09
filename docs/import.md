# The import contract

How the content project (`hhbd-content`) hands releases, artists, labels and images to hhbd
(#56). The contract is a JSON Schema,
[`app/docs/import.schema.json`](../app/docs/import.schema.json): hhbd-content validates its
batches against the schema at a commit of this repository, and the importer validates every
document against the same file (`Jkl_JsonSchema`) before it writes anything. This page explains
what the schema cannot say: what the fields mean, how a document finds its row, and what gets
written where.

The importer itself, its `make` targets and the job container it runs in land in steps under
#56; until then the schema and this page are what both sides build against.

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
  licence}` for `core` (names, titles, dates, types), `tracklist`, `cover`, `photos`, `facts`
  and `logo`. A Discogs field taken from the monthly dump says `"licence": "CC0"`; any other
  Discogs field counts as taken through the API, and the page credits it (#62).

## References

A reference (`artists[].ref`, `label.ref`, `members[].ref`, `credits[].ref`, `target.ref`) is
one of:

| Form | Points at |
|---|---|
| `artist:taco-hemingway` | the document with that `ref` earlier in the batch |
| `hhbd:artist:2241` | the row hhbd has with that id (`artist`, `label`, `album`, `song`) |
| `discogs:artist:4320863` | the row that has that external id |
| `name:Taco Hemingway` | the artist or label of that name |

## Finding a document's row

A document is matched to a row by its external ids first, its `hhbd_id` second and its natural
key third, and only a document that matches nothing makes a new row:

- a label by its name, an artist by its name, compared under the catalogue's collation
  (`utf8mb4_polish_ci`, so "Żabson" is not "Zabson" but "żabson" is "Żabson", #71);
- a release by its credited main artists, its title and its year give or take one.

Two external ids that point at two different rows are a conflict, reported, never resolved by
moving an id (#51).

## Fields and where they go

| Document | Field | Goes to |
|---|---|---|
| label | `name`, `website`, `profile` | `labels` |
| label | `logo` | the file under `content/l/`, `labels.logo` |
| artist | `name`, `type`, `real_name`, `website`, `profile` | `artists` |
| artist | `aliases` | `altnames_lookup` |
| artist | `members` | `band_lookup`, and the artist's type becomes `b` (#65) |
| artist | `cities` | `artist_city_lookup`, a city made when it is new (#64) |
| artist | `photos` | the files under `content/p/`, `artists_photos` with credit and licence (#61) |
| release | `title`, `release_type`, `legal`, `parent_release` | `albums` (#53) |
| release | `release_date`, `release_date_precision`, `announced` | `albums.year` as a whole date, its precision, `announced` (#54) |
| release | `artists` | `album_artist_lookup` with role, position and credited name (#58) |
| release | `label` | `albums.labelid`; `null` for a release without a label (#55) |
| release | `formats`, `catalog_numbers` | `albums.media_*`, `albums.catalog_*` (#53) |
| release | `cover` | the variants under `content/a/<variant>/`, `album_covers` (#60) |
| release | `tracklist` | `songs` (one reused when its `musicbrainz:recording` matches), `album_lookup` with disc and position (#59), credits in `feature_lookup`, `music_lookup`, `scratch_lookup`, `remix_lookup`, roles by name (#66) |
| image | `target`, `role`, `variants` or `photo` | as for a cover, photo or logo above, for a row hhbd already has |

Every row the import adds says `addedby = 1100`, every field it changes `updatedby = 1100` (#63);
every field it sets gets an `import_provenance` row, and every run an `import_runs` row (#52).

## Files

A file is named by its path in the batch (`files/<sha256>.jpg`) with its width, height, SHA-256
and MIME type. The importer reads the header and hashes the content and refuses the document if
they disagree, and it moves files into `content/` only after the document's rows are written. It
does not resize: the production image has no GD, so a cover's variants (`orig` up to 1500 px,
`600`, `300`, `75`) arrive made.
