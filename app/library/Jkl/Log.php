<?php

/**
 * One log line as the shared host reads every service's (#101; CONTRACT.md §9 in
 * gcloud-ovh-migrate): a JSON object with `time` (RFC 3339, UTC, to the millisecond), `level`
 * (debug, info, warn, error) and `msg`, then `logger`, the edge's `request_id`, and the event's
 * own fields, flat and in snake_case. The application's Zend_Log writes through
 * Jkl_Log_Formatter_Json, and PHP's own errors through the handlers installed here, so every
 * line the app container writes is one of these.
 */
class Jkl_Log
{
    /** Under the 16 KB at which Docker splits a line; a longer stack is cut, not spread */
    public const LINE_LIMIT = 16000;

    /** Zend_Log's priorities as the four levels: EMERG to ERR are errors, NOTICE an info */
    private const LEVELS = array(0 => 'error', 1 => 'error', 2 => 'error', 3 => 'error', 4 => 'warn', 5 => 'info', 6 => 'info', 7 => 'debug');

    public static function levelOf($priority)
    {
        return isset(self::LEVELS[(int) $priority]) ? self::LEVELS[(int) $priority] : 'error';
    }

    /**
     * The line, without its newline.
     *
     * @param array $fields logger, error, stack, duration_ms and the event's own, in snake_case
     */
    public static function line($level, $msg, array $fields = array(), $time = null)
    {
        $time = null === $time ? microtime(true) : (float) $time;
        $line = array(
            'time'  => gmdate('Y-m-d\TH:i:s', (int) $time) . sprintf('.%03dZ', (int) (($time - floor($time)) * 1000)),
            'level' => $level,
            'msg'   => '' === (string) $msg ? '(no message)' : (string) $msg,
        );
        $requestId = self::requestId();
        if (null !== $requestId && !isset($fields['request_id'])) {
            $line['request_id'] = $requestId;
        }
        $line += $fields;
        $json = self::encode($line);
        // Too long: the stack is cut first, then the message, so the line stays one line.
        foreach (array('stack', 'msg') as $key) {
            if (strlen($json) <= self::LINE_LIMIT || !isset($line[$key])) {
                continue;
            }
            $over = strlen($json) - self::LINE_LIMIT + 20;
            $line[$key] = mb_strcut((string) $line[$key], 0, max(0, strlen((string) $line[$key]) - $over), 'UTF-8') . ' [cut]';
            $json = self::encode($line);
        }
        return $json;
    }

    /** Writes the line to stderr, where PHP-FPM and docker pass it on */
    public static function write($level, $msg, array $fields = array())
    {
        $stream = fopen('php://stderr', 'a');
        fwrite($stream, self::line($level, $msg, $fields) . "\n");
        fclose($stream);
    }

    /**
     * The id the edge gives every request it proxies, X-Request-Id, replacing any a client
     * sent (CONTRACT.md §3); null outside a request, or for anything that does not look like one.
     */
    public static function requestId()
    {
        $id = isset($_SERVER['HTTP_X_REQUEST_ID']) ? (string) $_SERVER['HTTP_X_REQUEST_ID'] : '';
        return 1 === preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $id) ? $id : null;
    }

    /** `error` and `stack` for a throwable: its class and message, its frames in one field */
    public static function describe($e)
    {
        return array(
            'error' => get_class($e) . ': ' . $e->getMessage(),
            'stack' => $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString(),
        );
    }

    /**
     * PHP's errors, uncaught exceptions and fatal errors as lines of this format, each with
     * `error` and `stack`, instead of PHP's own text (log_errors is off for that reason).
     *
     * @param string $logger what the lines say wrote them: app, importer, ...
     */
    public static function installHandlers($logger = 'php')
    {
        set_error_handler(function ($number, $message, $file, $line) use ($logger) {
            if (!(error_reporting() & $number)) {
                return false;
            }
            $level = ($number & (E_WARNING | E_USER_WARNING | E_CORE_WARNING | E_COMPILE_WARNING)) ? 'warn'
                : (($number & (E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED)) ? 'info' : 'error');
            $e = new ErrorException($message, 0, $number, $file, $line);
            self::write($level, $message, array('logger' => $logger) + self::describe($e));
            return true;
        });
        set_exception_handler(function ($e) use ($logger) {
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                http_response_code(500);
            }
            self::write('error', 'Uncaught ' . get_class($e) . ': ' . $e->getMessage(), array('logger' => $logger) + self::describe($e));
        });
        register_shutdown_function(function () use ($logger) {
            $error = error_get_last();
            if (null === $error || !($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
                return;
            }
            self::write('error', $error['message'], array(
                'logger' => $logger,
                'error'  => 'PHP fatal error: ' . strtok($error['message'], "\n"),
                'stack'  => $error['file'] . ':' . $error['line'],
            ));
        });
    }

    private static function encode(array $line)
    {
        return json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
