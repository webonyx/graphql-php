<?php
namespace GraphQL\Error;

final class Warning
{
    const NAME_WARNING = 1;
    const ASSIGN_WARNING = 2;
    const CONFIG_WARNING = 4;
    const RESOLVE_TYPE_WARNING = 8;

    const ALL = 7;

    static $enableWarnings = self::ALL;

    static $warned = [];

    static function suppress($suppress = true)
    {
        if (true === $suppress) {
            self::$enableWarnings = 0;
        } else if (false === $suppress) {
            self::$enableWarnings = self::ALL;
        } else {
            $suppress = (int) $suppress;
            self::$enableWarnings &= ~$suppress;
        }
    }

    public static function enable($enable = true)
    {
        if (true === $enable) {
            self::$enableWarnings = self::ALL;
        } else if (false === $enable) {
            self::$enableWarnings = 0;
        } else {
            $enable = (int) $enable;
            self::$enableWarnings |= $enable;
        }
    }

    static function warnOnce($errorMessage, $warningId)
    {
        if ((self::$enableWarnings & $warningId) > 0 && !isset(self::$warned[$warningId])) {
            self::$warned[$warningId] = true;
            trigger_error($errorMessage, E_USER_WARNING);
        }
    }

    static function warn($errorMessage, $warningId)
    {
        if ((self::$enableWarnings & $warningId) > 0) {
            trigger_error($errorMessage, E_USER_WARNING);
        }
    }
}
