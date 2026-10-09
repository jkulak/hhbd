<?php

use PHPUnit\Framework\TestCase;

/**
 * What Jkl_Db::query() returns, as far as the model looks at it.
 */
class Model_Provenance_FakeStatement
{
    private $rowCount;

    public function __construct($rowCount)
    {
        $this->rowCount = $rowCount;
    }

    public function rowCount()
    {
        return $this->rowCount;
    }
}

/**
 * A stand-in for Jkl_Db: import_runs and import_provenance in memory, answering the statements
 * the model sends, and recording each with its bound values.
 */
class Model_Provenance_FakeDb
{
    public $runs = array();
    public $provenance = array();
    public $statements = array();
    private $lastInsertId = 0;

    public function query($query, array $bind = array())
    {
        $this->statements[] = array($query, $bind);
        if (0 === strpos($query, 'INSERT INTO import_runs')) {
            list($batch, $sha256, $mode) = $bind;
            $this->lastInsertId = count($this->runs) + 1;
            $this->runs[$this->lastInsertId] = array(
                'batch' => $batch, 'batch_sha256' => $sha256, 'mode' => $mode, 'finished' => null,
            );
            return new Model_Provenance_FakeStatement(1);
        }
        if (0 === strpos($query, 'UPDATE import_runs')) {
            list($created, $updated, $unchanged, $skipped, $refused, $report, $id) = $bind;
            if (!isset($this->runs[$id]) || null !== $this->runs[$id]['finished']) {
                return new Model_Provenance_FakeStatement(0);
            }
            $this->runs[$id] = array_merge($this->runs[$id], array(
                'finished' => '2026-10-09 12:00:00', 'created_count' => $created, 'updated_count' => $updated,
                'unchanged_count' => $unchanged, 'skipped_count' => $skipped, 'refused_count' => $refused,
                'report' => $report,
            ));
            return new Model_Provenance_FakeStatement(1);
        }
        // INSERT INTO import_provenance ... ON DUPLICATE KEY UPDATE
        list($entityType, $entityId, $field, $source, $sourceRef, $licence, $fetched, $runId) = $bind;
        $this->provenance["$entityType|$entityId|$field|$source"] = array(
            'entity_type' => $entityType, 'entity_id' => $entityId, 'field' => $field, 'source' => $source,
            'source_ref' => $sourceRef, 'licence' => $licence, 'fetched' => $fetched, 'run_id' => $runId,
            'added' => '2026-10-09 12:00:00',
        );
        return new Model_Provenance_FakeStatement(1);
    }

    public function lastInsertId()
    {
        return (string) $this->lastInsertId;
    }

    public function fetchAll($query, array $bind = array())
    {
        $this->statements[] = array($query, $bind);
        if (false !== strpos($query, 'FROM import_runs')) {
            return isset($this->runs[$bind[0]]) ? array($this->runs[$bind[0]]) : array();
        }
        list($entityType, $entityId) = $bind;
        $rows = array_values(array_filter($this->provenance, function ($row) use ($entityType, $entityId) {
            return $row['entity_type'] === $entityType && $row['entity_id'] === $entityId;
        }));
        // Sorted only when the query asks for it, as the database would.
        if (false !== strpos($query, 'ORDER BY field, source')) {
            usort($rows, function ($a, $b) {
                return strcmp($a['field'] . '|' . $a['source'], $b['field'] . '|' . $b['source']);
            });
        }
        return $rows;
    }
}

class Model_Provenance_ApiTest extends TestCase
{
    public const SHA = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    private $db;
    private $api;

    protected function setUp(): void
    {
        $this->db = new Model_Provenance_FakeDb();
        $this->api = new Model_Provenance_Api($this->db);
    }

    public function testARunOpensWithItsBatchAndMode(): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', strtoupper(self::SHA), 'apply');

        $this->assertSame(1, $runId);
        $this->assertSame('batch-2016.ndjson', $this->db->runs[1]['batch']);
        $this->assertSame(self::SHA, $this->db->runs[1]['batch_sha256']);
        $this->assertSame('apply', $this->db->runs[1]['mode']);
    }

    public function testAClosedRunKeepsItsTotalsAndReport(): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', self::SHA, 'apply');
        $this->api->finishRun($runId, array('created' => 3, 'unchanged' => 7), array('totals' => array('created' => 3), 'warnings' => array('Bęsiu: no cover')));

        $run = $this->db->runs[$runId];
        $this->assertSame(array(3, 0, 7, 0, 0), array($run['created_count'], $run['updated_count'], $run['unchanged_count'], $run['skipped_count'], $run['refused_count']));
        $this->assertSame('{"totals":{"created":3},"warnings":["Bęsiu: no cover"]}', $run['report']);
    }

    public function testARunClosesOnlyOnce(): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', self::SHA, 'dry-run');
        $this->api->finishRun($runId, array());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Run 1 is not open');
        $this->api->finishRun($runId, array());
    }

    /**
     * @dataProvider badCountsProvider
     */
    public function testATotalThatIsNotAnActionOrNotACountIsRefused(array $counts): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', self::SHA, 'apply');

        $this->expectException(InvalidArgumentException::class);
        $this->api->finishRun($runId, $counts);
    }

    public function badCountsProvider(): array
    {
        return array(
            'an action the report does not have' => array(array('deleted' => 1)),
            'a negative count' => array(array('created' => -1)),
            'a count as a string' => array(array('created' => '3')),
        );
    }

    /**
     * @dataProvider badRunProvider
     */
    public function testARunWithoutAProperBatchOrModeIsRefused(string $batch, string $sha256, string $mode): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->api->startRun($batch, $sha256, $mode);
    }

    public function badRunProvider(): array
    {
        return array(
            'no batch name' => array(' ', self::SHA, 'apply'),
            'a hash that is not SHA-256' => array('batch-2016.ndjson', 'd41d8cd98f00b204e9800998ecf8427e', 'apply'),
            'a mode the importer does not have' => array('batch-2016.ndjson', self::SHA, 'force'),
        );
    }

    public function testAFieldRecordedInARunIsListedForItsRow(): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', self::SHA, 'apply');
        $this->api->record($runId, 'album', 535, 'title', 'discogs', 'discogs:master:1234567', 'CC0', '2026-10-09T12:30:00Z');

        $provenance = $this->api->getForEntity('album', 535);

        $this->assertTrue($provenance->cameFrom('title', 'discogs'));
        $this->assertFalse($provenance->cameFrom('title', 'musicbrainz'));
        $this->assertFalse($provenance->cameFrom('cover', 'discogs'));
        $this->assertSame('discogs:master:1234567', $provenance->items[0]->sourceRef);
        $this->assertSame('CC0', $provenance->items[0]->licence);
        $this->assertSame($runId, $provenance->items[0]->runId);
        $this->assertCount(0, $this->api->getForEntity('album', 536)->items);
    }

    public function testRecordingTheSameFieldFromTheSameSourceAgainReplacesIt(): void
    {
        $first = $this->api->startRun('batch-2016.ndjson', self::SHA, 'apply');
        $this->api->record($first, 'artist', 35, 'photo', 'commons', 'File:Mes 2015.jpg', 'CC BY-SA 3.0', '2026-10-09T12:00:00Z');
        $this->api->finishRun($first, array('created' => 1));
        $second = $this->api->startRun('batch-2016-fix.ndjson', self::SHA, 'apply');
        $this->api->record($second, 'artist', 35, 'photo', 'commons', 'File:Mes 2019.jpg', 'CC BY 4.0', '2026-10-10T08:00:00Z');

        $rows = $this->api->getForEntity('artist', 35)->items;

        $this->assertCount(1, $rows);
        $this->assertSame(array('File:Mes 2019.jpg', 'CC BY 4.0', '2026-10-10 08:00:00', $second), array($rows[0]->sourceRef, $rows[0]->licence, $rows[0]->fetched, $rows[0]->runId));
    }

    public function testAFieldTwoSourcesSuppliedHasTwoRowsInOrder(): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', self::SHA, 'apply');
        $this->api->record($runId, 'album', 535, 'year', 'musicbrainz', 'b1a9c0e9-d987-4042-ae91-78d6a3267d69', null, '2026-10-09T12:00:00Z');
        $this->api->record($runId, 'album', 535, 'year', 'discogs', '1234567', null, '2026-10-09T12:00:00Z');
        $this->api->record($runId, 'album', 535, 'cover', 'coverartarchive', 'https://coverartarchive.org/release-group/b1a9c0e9-d987-4042-ae91-78d6a3267d69/front', 'tolerated', '2026-10-09T12:00:00Z');

        $provenance = $this->api->getForEntity('album', 535);
        $sources = array_map(function ($item) {
            return $item->source;
        }, $provenance->getForField('year'));

        $this->assertSame(array('discogs', 'musicbrainz'), $sources);
        $this->assertSame(array('cover', 'year', 'year'), array_map(function ($item) {
            return $item->field;
        }, $provenance->items));
        $this->assertSame(array(), $provenance->getForField('description'));
    }

    public function testADryRunRecordsNoProvenance(): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', self::SHA, 'dry-run');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Run 1 is a dry run, which records no provenance');
        $this->api->record($runId, 'album', 535, 'title', 'discogs', '1234567', null, '2026-10-09T12:00:00Z');
    }

    public function testAClosedRunRecordsNoProvenance(): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', self::SHA, 'apply');
        $this->api->finishRun($runId, array());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Run 1 is closed');
        $this->api->record($runId, 'album', 535, 'title', 'discogs', '1234567', null, '2026-10-09T12:00:00Z');
    }

    public function testARunClosedElsewhereRecordsNoProvenanceEither(): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', self::SHA, 'apply');
        $this->api->finishRun($runId, array());
        $another = new Model_Provenance_Api($this->db);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Run 1 is closed');
        $another->record($runId, 'album', 535, 'title', 'discogs', '1234567', null, '2026-10-09T12:00:00Z');
    }

    public function testARunNobodyStartedRecordsNoProvenance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No run 7');
        $this->api->record(7, 'album', 535, 'title', 'discogs', '1234567', null, '2026-10-09T12:00:00Z');
    }

    /**
     * @dataProvider badRecordProvider
     */
    public function testAMalformedRecordIsRefusedBeforeItReachesTheDatabase(string $entityType, int $entityId, string $field, string $source, string $sourceRef, ?string $licence): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', self::SHA, 'apply');
        $statements = count($this->db->statements);

        try {
            $this->api->record($runId, $entityType, $entityId, $field, $source, $sourceRef, $licence, '2026-10-09T12:00:00Z');
            $this->fail('The record was accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertCount($statements, $this->db->statements);
        }
    }

    public function badRecordProvider(): array
    {
        return array(
            'a source nobody imports from' => array('album', 535, 'title', 'spotify', '123', null),
            'an entity the catalog does not have' => array('concert', 1, 'title', 'discogs', '123', null),
            'no entity id' => array('album', 0, 'title', 'discogs', '123', null),
            'a field name in capitals' => array('album', 535, 'Title', 'discogs', '123', null),
            'no field name' => array('album', 535, '', 'discogs', '123', null),
            'no reference' => array('album', 535, 'title', 'discogs', ' ', null),
            'an empty licence' => array('album', 535, 'title', 'discogs', '123', ''),
        );
    }

    /**
     * @dataProvider fetchedProvider
     */
    public function testAFetchTimeIsStoredInUtc(string $written, string $stored): void
    {
        $this->assertSame($stored, Model_Provenance_Api::toUtc($written));
    }

    public function fetchedProvider(): array
    {
        return array(
            'UTC with Z' => array('2026-10-09T12:30:00Z', '2026-10-09 12:30:00'),
            'Polish summer time' => array('2026-10-09T14:30:00+02:00', '2026-10-09 12:30:00'),
            'no zone, taken as UTC' => array('2026-10-09 12:30:00', '2026-10-09 12:30:00'),
            'fractions of a second dropped' => array('2026-10-09T12:30:00.734Z', '2026-10-09 12:30:00'),
        );
    }

    /**
     * @dataProvider badFetchedProvider
     */
    public function testAFetchTimeThatIsNotISO8601IsRefused(string $written): void
    {
        $this->expectException(InvalidArgumentException::class);
        Model_Provenance_Api::toUtc($written);
    }

    public function badFetchedProvider(): array
    {
        return array(
            'words' => array('9 Oct 2026 12:30'),
            'a day with no time' => array('2026-10-09'),
            'a day that does not exist' => array('2026-02-30T12:00:00Z'),
            'nothing' => array(''),
        );
    }

    public function testValuesReachTheDatabaseAsBoundParametersNotInTheQuery(): void
    {
        $runId = $this->api->startRun('batch-2016.ndjson', self::SHA, 'apply');
        $this->api->record($runId, 'label', 58, 'name', 'discogs', "271903' OR '1'='1", null, '2026-10-09T12:00:00Z');
        $this->api->finishRun($runId, array('created' => 1), array('note' => "it's done"));

        foreach ($this->db->statements as list($query, $bind)) {
            $this->assertStringNotContainsString('271903', $query);
            $this->assertStringNotContainsString('batch-2016', $query);
            $this->assertStringNotContainsString("it's", $query);
        }
    }

    public function testEverySourceThatGivesIdsIsAProvenanceSourceUnderTheSameName(): void
    {
        // Barcodes and ISRCs are ids without a source to read anything from.
        $idSources = array_diff(array_keys(Model_ExternalId_Api::VOCABULARY), array('barcode', 'isrc'));

        $this->assertSame(array(), array_values(array_diff($idSources, Model_Provenance_Api::SOURCES)));
    }
}
