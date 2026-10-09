<?php

/**
 * Describes the cover files already on the content volume in album_covers (#60).
 *
 *   php app/tools/covers.php backfill [--dry-run]
 *
 * For every album naming a cover, the file content/a/<cover> becomes a 300, 600 or orig row by
 * its size, and its thumbnail content/a/th/<cover without extension>-th.jpg a 75 row, each with
 * width, height, SHA-256 and MIME type, source "legacy" and no licence. A file the volume lacks
 * gets no row (#47). Running it again adds nothing: album, variant and hash are unique. A dry
 * run writes the same rows in a transaction it rolls back, so it reports exactly what a run
 * would, rows already there included.
 *
 * It reads DB_HOST, DB_NAME, DB_USER and DB_PASSWORD from the environment, as the application
 * does, and the files under CONTENT_DIR (/var/www/html/content by default). getimagesize() is in
 * PHP's core, so it needs no GD, which the production image lacks.
 */

$mode = isset($argv[1]) ? $argv[1] : '';
$dryRun = in_array('--dry-run', $argv, true);
if ('backfill' !== $mode) {
    fwrite(STDERR, "usage: php covers.php backfill [--dry-run]\n");
    exit(2);
}

$contentDir = rtrim(getenv('CONTENT_DIR') ?: '/var/www/html/content', '/');
if (!is_dir($contentDir . '/a')) {
    fwrite(STDERR, "x covers: no $contentDir/a; is the content volume mounted?\n");
    exit(1);
}

$db = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_NAME')),
    getenv('DB_USER'),
    getenv('DB_PASSWORD'),
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);

if ($dryRun) {
    $db->beginTransaction();
}

$insert = $db->prepare(
    'INSERT IGNORE INTO album_covers (albumid, variant, path, width, height, sha256, mime, source, main)
     VALUES (?, ?, ?, ?, ?, ?, ?, \'legacy\', \'y\')'
);

$counts = array('orig' => 0, '600' => 0, '300' => 0, '75' => 0, 'missing' => 0, 'unreadable' => 0, 'already' => 0);
$albums = $db->query("SELECT id, cover FROM albums WHERE cover IS NOT NULL AND cover <> '' ORDER BY id");
foreach ($albums as $album) {
    $files = array(
        'a/' . $album['cover'] => null,
        'a/th/' . substr($album['cover'], 0, -4) . '-th.jpg' => '75',
    );
    foreach ($files as $path => $variant) {
        $file = $contentDir . '/' . $path;
        if (!is_file($file)) {
            $counts['missing']++;
            continue;
        }
        $size = @getimagesize($file);
        if (false === $size) {
            $counts['unreadable']++;
            fwrite(STDERR, "  not an image: content/$path\n");
            continue;
        }
        list($width, $height) = $size;
        if (null === $variant) {
            $longer = max($width, $height);
            $variant = $longer <= 300 ? '300' : ($longer <= 600 ? '600' : 'orig');
        }
        $insert->execute(array(
            (int) $album['id'], $variant, $path, $width, $height, hash_file('sha256', $file), $size['mime'],
        ));
        $counts[$insert->rowCount() ? $variant : 'already']++;
    }
}

if ($dryRun) {
    $db->rollBack();
}

printf(
    "%s covers: orig %d, 600 %d, 300 %d, 75 %d added; %d already there; %d files missing, %d unreadable\n",
    $dryRun ? 'would add' : 'ok',
    $counts['orig'],
    $counts['600'],
    $counts['300'],
    $counts['75'],
    $counts['already'],
    $counts['missing'],
    $counts['unreadable']
);
