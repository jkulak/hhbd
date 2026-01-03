<?php

declare(strict_types=1);

$appDir  = dirname(__DIR__);          // .../app
$repoDir = realpath($appDir . '/..'); // repo root
if ($repoDir === false) {
    exit(0);
}

$hooksDir = $repoDir . '/.git/hooks';

// Not a git checkout (or hooks dir missing) => no-op
if (!is_dir($hooksDir)) {
    exit(0);
}

$hooks = ['pre-commit', 'commit-msg', 'pre-push'];
$updated = [];

foreach ($hooks as $hook) {
    $src = $appDir . '/hooks/' . $hook;
    $dst = $hooksDir . '/' . $hook;

    if (!is_file($src)) {
        continue;
    }

    $needsUpdate = !is_file($dst)
        || hash_file('sha256', $src) !== hash_file('sha256', $dst);

    if ($needsUpdate) {
        if (!copy($src, $dst)) {
            fwrite(STDERR, "❌ ERROR: failed to copy {$hook}\n");
            exit(1);
        }
        chmod($dst, 0755);
        $updated[] = $hook;
    }
}

echo $updated
    ? "✅ Hooks updated: " . implode(', ', $updated) . "\n"
    : "✅ Hooks up to date\n";
