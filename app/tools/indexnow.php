<?php

/**
 * Tells IndexNow every address the sitemaps list (#220), for the first time and after a gap in
 * what imports and admins told it as they went.
 *
 *   php app/tools/indexnow.php all [--dry-run]
 *
 * The key comes from INDEXNOW_KEY, as the application's does; without one it sends nothing and
 * says so. A dry run counts the addresses and the requests they make, and sends nothing. Each
 * request's result goes to stderr as a JSON line in the shared host's format (#101, Jkl_Log).
 */

$mode = isset($argv[1]) ? $argv[1] : '';
$dryRun = in_array('--dry-run', $argv, true);
if ('all' !== $mode) {
    fwrite(STDERR, "usage: php indexnow.php all [--dry-run]\n");
    exit(2);
}

require __DIR__ . '/bootstrap.php';

$paths = array();
foreach (Model_Sitemap_Api::KINDS as $kind) {
    $paths = array_merge($paths, Model_Sitemap_Api::getInstance()->paths($kind));
}
$paths = array_values(array_unique($paths));
$requests = (int) ceil(count($paths) / Jkl_IndexNow::BATCH);

$indexNow = Model_IndexNow_Api::getInstance();
if (null === $indexNow->key()) {
    Jkl_Log::write('warn', sprintf('No IndexNow key (INDEXNOW_KEY): %d addresses not sent', count($paths)), array('logger' => 'indexnow'));
    exit(1);
}
if ($dryRun) {
    Jkl_Log::write('info', sprintf('Dry run: %d addresses in %d %s, none sent', count($paths), $requests, 1 === $requests ? 'request' : 'requests'), array('logger' => 'indexnow', 'urls' => count($paths)));
    exit(0);
}
$results = $indexNow->submit($paths, 'every sitemap');
foreach ((array) $results as $result) {
    if (!in_array($result['status'], array(200, 202), true)) {
        exit(1);
    }
}
exit(null === $results ? 1 : 0);
