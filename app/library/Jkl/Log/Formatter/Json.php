<?php

/**
 * Zend_Log's events as Jkl_Log lines (#101): the priority as a level, the message as `msg`, the
 * time in UTC, and every extra the event carries as a field of its own.
 */
class Jkl_Log_Formatter_Json extends Zend_Log_Formatter_Abstract
{
    /** What Zend_Log puts in every event, which the line says its own way */
    private const OWN = array('timestamp', 'message', 'priority', 'priorityName');

    public static function factory($options)
    {
        return new self();
    }

    public function format($event)
    {
        $fields = array('logger' => 'app');
        foreach ($event as $key => $value) {
            if (in_array($key, self::OWN, true)) {
                continue;
            }
            if ($value instanceof Throwable) {
                $fields += Jkl_Log::describe($value);
                continue;
            }
            $fields[$key] = is_scalar($value) || null === $value ? $value : json_decode(json_encode($value), true);
        }
        return Jkl_Log::line(Jkl_Log::levelOf($event['priority']), $event['message'], $fields) . PHP_EOL;
    }
}
