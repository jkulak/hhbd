<?php

/**
 * Reads an import batch into the catalogue (#56); docs/import.md describes the batch.
 *
 *   php app/tools/import.php <batch directory> --dry-run|--apply [--release-now] [--report=<file>]
 *
 * A dry run checks every document and image and writes nothing but its run in import_runs; an
 * apply writes the rows and moves the images into CONTENT_DIR (/var/www/html/content by
 * default). What it would publish waits in the release queue for the daily release (#258),
 * which the report's queued lists; --release-now publishes it at once. Each document's line
 * goes to stderr as it is read, as a JSON line in the shared host's format (#101, Jkl_Log),
 * and so do the run's summary and every refusal; the report, JSON, goes to stdout or the file
 * --report names, and into import_runs. It exits 0 when every document was read, 1 when one or
 * more were refused, 2 when the batch could not be read at all.
 */

$args = array_slice($argv, 1);
$modes = array_values(array_intersect($args, array('--dry-run', '--apply')));
$reportFile = null;
$batch = null;
$releaseNow = false;
foreach ($args as $arg) {
    if (0 === strpos($arg, '--report=')) {
        $reportFile = substr($arg, 9);
    } elseif ('--release-now' === $arg) {
        $releaseNow = true;
    } elseif (0 !== strpos($arg, '--')) {
        $batch = $arg;
    } elseif (!in_array($arg, array('--dry-run', '--apply'), true)) {
        $batch = null;
        break;
    }
}
if (null === $batch || 1 !== count($modes)) {
    fwrite(STDERR, "usage: php import.php <batch directory> --dry-run|--apply [--release-now] [--report=<file>]\n");
    exit(2);
}
if (!is_dir($batch)) {
    fwrite(STDERR, "x import: no directory $batch\n");
    exit(2);
}

require __DIR__ . '/bootstrap.php';

$mode = substr($modes[0], 2);
$started = microtime(true);
try {
    $importer = new Model_Import_Importer(getenv('CONTENT_DIR') ?: '/var/www/html/content', $releaseNow);
    $report = $importer->import($batch, $mode, function ($line) {
        Jkl_Log::write('info', $line, array('logger' => 'importer'));
    });
} catch (Exception $e) {
    Jkl_Log::write('error', 'The batch could not be read: ' . $e->getMessage(), array('logger' => 'importer') + Jkl_Log::describe($e));
    exit(2);
}

// The pages the run made or changed, told to IndexNow as they appear (#220); a failure is a log
// line, never the import's.
$indexNow = Model_IndexNow_Api::getInstance();
if ('apply' === $mode && null !== $indexNow->key()) {
    $indexNow->submit($indexNow->importPaths($report), 'import run ' . $report['run']);
}

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
if (null === $reportFile) {
    echo $json;
} else {
    file_put_contents($reportFile, $json);
}

$totals = array();
$counts = array();
foreach ($report['totals'] as $action => $count) {
    $totals[] = "$count $action";
    $counts[$action . '_count'] = $count;
}
$totals[] = count($report['queued']) . ' queued for release';
$counts['queued_count'] = count($report['queued']);
Jkl_Log::write('info', sprintf('%s run %d of %s: %s', $mode, $report['run'], $report['batch'], implode(', ', $totals)), array(
    'logger'      => 'importer',
    'run'         => $report['run'],
    'mode'        => $mode,
    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
) + $counts);
foreach ($report['documents'] as $document) {
    foreach ($document['errors'] as $error) {
        Jkl_Log::write('warn', sprintf('line %d %s refused: %s', $document['line'], $document['ref'] ?: $document['kind'], $error), array(
            'logger' => 'importer',
            'run'    => $report['run'],
            'line'   => $document['line'],
        ));
    }
}
exit($report['totals']['refused'] > 0 ? 1 : 0);
