<?php

/**
 * Prepended to every PHP script in production (auto_prepend_file in conf/php/production.ini):
 * PHP's errors, uncaught exceptions and fatal errors become JSON lines in the shared host's
 * format (#101) for every script, the front controller, the tools, and any other, from its first
 * line on. log_errors is off there, so without this an error would leave no line at all.
 */
require_once __DIR__ . '/../Log.php';
Jkl_Log::installHandlers(PHP_SAPI === 'cli' ? 'tool' : 'php');
