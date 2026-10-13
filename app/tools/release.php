<?php

/**
 * The release queue (#258): what an import queued, and the daily run that publishes the next
 * few albums of it.
 *
 *   php app/tools/release.php queue                         what waits, and when it is all out
 *   php app/tools/release.php run [--count=N] [--dry-run]   the day's release
 *
 * A run publishes a random number of albums from RELEASE_MIN to RELEASE_MAX (5 and 10 without
 * them), or N; each is an operation of the edit journal, written as the import's user, and the
 * pages it shows are told to IndexNow. A dry run names the albums a run would publish and
 * changes nothing. A run's lines go to stderr as JSON in the shared host's format (#101,
 * Jkl_Log), as the host's journal collects them. It exits 0 when every album went out, nothing
 * waiting included, 1 when one was refused, 2 on a wrong call.
 */

$command = isset($argv[1]) ? $argv[1] : '';
$dryRun = false;
$count = null;
foreach (array_slice($argv, 2) as $arg) {
    if ('--dry-run' === $arg) {
        $dryRun = true;
    } elseif (preg_match('/^--count=([0-9]+)$/', $arg, $m)) {
        $count = (int) $m[1];
    } else {
        $command = '';
    }
}
if (!in_array($command, array('queue', 'run'), true)) {
    fwrite(STDERR, "usage: php release.php queue | run [--count=N] [--dry-run]\n");
    exit(2);
}

require __DIR__ . '/bootstrap.php';

$releases = Model_Release_Api::getInstance();
$min = false === getenv('RELEASE_MIN') || '' === getenv('RELEASE_MIN') ? Model_Release_Api::MIN : (int) getenv('RELEASE_MIN');
$max = false === getenv('RELEASE_MAX') || '' === getenv('RELEASE_MAX') ? Model_Release_Api::MAX : (int) getenv('RELEASE_MAX');

try {
    $howMany = null === $count ? Model_Release_Api::howMany($min, $max) : $count;
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'x release: ' . $e->getMessage() . "\n");
    exit(2);
}

if ('queue' === $command) {
    $waiting = $releases->count();
    if (0 === $waiting) {
        echo "Nothing waits in the release queue.\n";
    } else {
        $years = array();
        foreach ($releases->countByYear() as $year => $n) {
            $years[] = ('' === $year ? 'no year' : $year) . ': ' . $n;
        }
        printf("%d %s (%s).\n", $waiting, 1 === $waiting ? 'album waits' : 'albums wait', implode(', ', $years));
        printf(
            "At %d-%d a day, the last comes out about %s.\n\nNext:\n",
            $min,
            $max,
            Model_Release_Api::emptiesOn($waiting, $min, $max, date('Y-m-d'))
        );
        foreach ($releases->waiting(10) as $i => $album) {
            printf(
                "%3d. album %d  %s - %s (%s), queued %s\n",
                $i + 1,
                $album['id'],
                $album['artists'],
                $album['title'],
                null === $album['year'] ? 'no date' : $album['year'],
                substr($album['queued'], 0, 10)
            );
        }
    }
    foreach ($releases->stranded() as $artist) {
        printf("Artist %d, %s, waits with no unpublished album to show it; DO=\"set artists %d queued --null\" shows it.\n", $artist['id'], $artist['name'], $artist['id']);
    }
    exit(0);
}

$log = function ($level, $message, array $fields = array()) {
    Jkl_Log::write($level, $message, array('logger' => 'release') + $fields);
};
$started = microtime(true);
$userId = Model_Provenance_Api::getInstance()->getImportUserId();

if ($dryRun) {
    foreach ($releases->waiting($howMany) as $album) {
        $log('info', sprintf('would release album %d, %s - %s', $album['id'], $album['artists'], $album['title']), array('album' => (int) $album['id']));
    }
    $log('info', sprintf('dry run: %d of %d waiting would be released; nothing written', min($howMany, $releases->count()), $releases->count()));
    exit(0);
}

$done = $releases->release($howMany, $userId, 'the daily release (#258)');
$indexNow = Model_IndexNow_Api::getInstance();
$paths = array();
$refused = 0;
foreach ($done as $album) {
    if (isset($album['error'])) {
        $refused++;
        $log('error', sprintf('album %d was not released: %s', $album['id'], $album['error']), array('album' => $album['id']));
        continue;
    }
    $log('info', sprintf('released album %d, %s, as operation %d: %d rows', $album['id'], $album['title'], $album['operation'], count($album['lines'])), array(
        'album'     => $album['id'],
        'operation' => $album['operation'],
    ));
    $paths = array_merge($paths, $indexNow->releasePaths($album['id']));
}
// A failure is a log line, never the release's.
$indexNow->submit(array_values(array_unique($paths)), 'the daily release');
$log($refused > 0 ? 'error' : 'info', sprintf('released %d (today up to %d), %d still waiting', count($done) - $refused, $howMany, $releases->count()), array(
    'released_count' => count($done) - $refused,
    'refused_count'  => $refused,
    'waiting_count'  => $releases->count(),
    'duration_ms'    => (int) round((microtime(true) - $started) * 1000),
));
exit($refused > 0 ? 1 : 0);
