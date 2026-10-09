<?php

/**
 * Reads an import batch into the catalogue (#56); docs/import.md describes the batch.
 *
 *   php app/tools/import.php <batch directory> --dry-run|--apply [--report=<file>]
 *
 * A dry run checks every document and image and writes nothing but its run in import_runs; an
 * apply writes the rows and moves the images into CONTENT_DIR (/var/www/html/content by
 * default). Each document's line goes to stderr as it is read; the report, JSON, goes to stdout
 * or the file --report names, and into import_runs. It exits 0 when every document was read, 1
 * when one or more were refused, 2 when the batch could not be read at all.
 */

$args = array_slice($argv, 1);
$modes = array_values(array_intersect($args, array('--dry-run', '--apply')));
$reportFile = null;
$batch = null;
foreach ($args as $arg) {
    if (0 === strpos($arg, '--report=')) {
        $reportFile = substr($arg, 9);
    } elseif (0 !== strpos($arg, '--')) {
        $batch = $arg;
    }
}
if (null === $batch || 1 !== count($modes)) {
    fwrite(STDERR, "usage: php import.php <batch directory> --dry-run|--apply [--report=<file>]\n");
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
    $importer = new Model_Import_Importer(getenv('CONTENT_DIR') ?: '/var/www/html/content');
    $report = $importer->import($batch, $mode, function ($line) {
        fwrite(STDERR, $line . "\n");
    });
} catch (Exception $e) {
    fwrite(STDERR, 'x import: ' . $e->getMessage() . "\n");
    exit(2);
}

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
if (null === $reportFile) {
    echo $json;
} else {
    file_put_contents($reportFile, $json);
}

$totals = array();
foreach ($report['totals'] as $action => $count) {
    $totals[] = "$count $action";
}
fwrite(STDERR, sprintf(
    "%s run %d of %s: %s, in %.1f s\n",
    $mode,
    $report['run'],
    $report['batch'],
    implode(', ', $totals),
    microtime(true) - $started
));
foreach ($report['documents'] as $document) {
    foreach ($document['errors'] as $error) {
        fwrite(STDERR, sprintf("  line %d %s: %s\n", $document['line'], $document['ref'] ?: $document['kind'], $error));
    }
}
exit($report['totals']['refused'] > 0 ? 1 : 0);
