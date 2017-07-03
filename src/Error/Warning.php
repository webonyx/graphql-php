<?php
namespace GraphQL\Error;

final class Warning
{
    static $supressWarnings = false;

    static $warned = [];

    static function supress($set = true)
    {
        self::$supressWarnings = $set;
    }

    static function warnOnce($errorMessage, $errorId = null)
    {
        $errorId = $errorId ?: $errorMessage;

        if (!self::$supressWarnings && !isset(self::$warned[$errorId])) {
            self::$warned[$errorId] = true;
            trigger_error($errorMessage, E_USER_WARNING);
        }
    }
}
