<?php

/**
 * An admin's edit to the catalogue, journalled and undoable (#115); database/README.md says
 * when an edit goes this way and when through a migration.
 *
 *   php app/tools/edit.php <operation> <arguments...> --by=<admin> --why=<text> [--apply]
 *   php app/tools/edit.php -        the same, as NUL-separated arguments on stdin
 *
 *   merge-albums <from> <into>      merge-artists <from> <into>
 *   delete-album <id>               delete-artist <id>               delete-label <id>
 *   set <albums|artists|labels|songs> <id> <column> <value|--null>
 *   set <albums|artists|labels|songs> <id> <JSON object of columns>   one operation (#145)
 *   undo <operation>
 *
 * --by is the display name of an hhbd user who is an admin, --why says why; without both it
 * refuses before it reads anything. Without --apply it is a dry run: the same operation in a
 * transaction rolled back, which prints what an apply would change. Each row changed is one
 * line on stdout. It exits 0 when the operation went through, 1 when it was refused, 2 on a
 * wrong call.
 */

$args = array_slice($argv, 1);
if (array('-') === $args) {
    // From make ovh-edit over ssh: no shell between here and the caller re-reads the arguments.
    // Each ends in a NUL, so only the piece after the last one goes: an empty VALUE is a value.
    $args = explode("\0", stream_get_contents(STDIN));
    if ('' === end($args)) {
        array_pop($args);
    }
}
$by = $why = null;
$apply = false;
$words = array();
foreach ($args as $arg) {
    if (0 === strpos($arg, '--by=')) {
        $by = trim(substr($arg, 5));
    } elseif (0 === strpos($arg, '--why=')) {
        $why = trim(substr($arg, 6));
    } elseif ('--apply' === $arg) {
        $apply = true;
    } elseif ('--null' === $arg) {
        $words[] = null;
    } else {
        $words[] = $arg;
    }
}
$usage = "usage: php edit.php <operation> <arguments...> --by=<admin> --why=<text> [--apply]\n";
if (empty($words)) {
    fwrite(STDERR, $usage);
    exit(2);
}
if ('' === (string) $by || '' === (string) $why) {
    fwrite(STDERR, "x edit: every edit says who makes it and why: --by=<an hhbd admin's name> --why=<text>\n");
    exit(1);
}

require __DIR__ . '/bootstrap.php';

$edit = Model_Edit_Api::getInstance();
$db = Jkl_Db::getInstance();
$userId = $edit->adminId($by);
if (null === $userId) {
    fwrite(STDERR, sprintf("x edit: \"%s\" is no hhbd admin\n", $by));
    exit(1);
}
$operation = array_shift($words);

$db->beginTransaction();
try {
    $id = $edit->run($operation, $words, $userId, $why, 'cli');
} catch (Exception $e) {
    $db->rollBack();
    fwrite(STDERR, 'x edit: ' . $e->getMessage() . "\n");
    exit($e instanceof InvalidArgumentException ? 2 : 1);
}
$lines = $edit->getLines();
foreach ($lines as $line) {
    echo $line, "\n";
}
if ($apply) {
    $db->commit();
    printf(
        "ok operation %d, %s: %d rows changed%s\n",
        $id,
        $operation,
        count($lines),
        'undo' === $operation ? '' : sprintf('; DO="undo %d" takes it back', $id)
    );
} else {
    $db->rollBack();
    printf("dry run of %s: %d rows would change; nothing written, MODE=apply writes it\n", $operation, count($lines));
}
