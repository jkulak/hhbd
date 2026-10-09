<?php

/**
 * Records the size, type and hash of the artist photos already on the content volume (#61).
 *
 *   php app/tools/photos.php backfill [--dry-run]
 *
 * For every artists_photos row whose file content/p/<filename> exists and has no hash yet, it
 * fills width, height, SHA-256 and MIME type. A file the volume lacks keeps its row empty (#47).
 * Running it again changes nothing. It reads DB_HOST, DB_NAME, DB_USER and DB_PASSWORD from the
 * environment and the files under CONTENT_DIR (/var/www/html/content by default), and needs no
 * GD: getimagesize() is in PHP's core.
 */

$mode = isset($argv[1]) ? $argv[1] : '';
$dryRun = in_array('--dry-run', $argv, true);
if ('backfill' !== $mode) {
    fwrite(STDERR, "usage: php photos.php backfill [--dry-run]\n");
    exit(2);
}

$contentDir = rtrim(getenv('CONTENT_DIR') ?: '/var/www/html/content', '/');
if (!is_dir($contentDir . '/p')) {
    fwrite(STDERR, "x photos: no $contentDir/p; is the content volume mounted?\n");
    exit(1);
}

$db = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_NAME')),
    getenv('DB_USER'),
    getenv('DB_PASSWORD'),
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);

$update = $db->prepare('UPDATE artists_photos SET width = ?, height = ?, sha256 = ?, mime = ? WHERE id = ? AND sha256 IS NULL');

$counts = array('filled' => 0, 'missing' => 0, 'unreadable' => 0, 'duplicate' => 0);
$rows = $db->query("SELECT id, artistid, filename FROM artists_photos WHERE sha256 IS NULL AND filename <> '' ORDER BY id");
foreach ($rows as $row) {
    $file = $contentDir . '/p/' . $row['filename'];
    if (!is_file($file)) {
        $counts['missing']++;
        continue;
    }
    $size = @getimagesize($file);
    if (false === $size) {
        $counts['unreadable']++;
        fwrite(STDERR, "  not an image: content/p/{$row['filename']}\n");
        continue;
    }
    if ($dryRun) {
        $counts['filled']++;
        continue;
    }
    try {
        $update->execute(array($size[0], $size[1], hash_file('sha256', $file), $size['mime'], (int) $row['id']));
        $counts['filled'] += $update->rowCount();
    } catch (PDOException $e) {
        // The same file twice for one artist: the unique key keeps the first, and says so.
        $counts['duplicate']++;
        fwrite(STDERR, "  the same file as another photo of artist {$row['artistid']}: content/p/{$row['filename']}\n");
    }
}

printf(
    "%s photos: %d filled; %d files missing, %d unreadable, %d duplicates\n",
    $dryRun ? 'would fill' : 'ok',
    $counts['filled'],
    $counts['missing'],
    $counts['unreadable'],
    $counts['duplicate']
);
