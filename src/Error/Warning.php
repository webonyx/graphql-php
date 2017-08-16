<?php
namespace GraphQL\Error;

final class Warning
{
    const NAME_WARNING = 1;
    const ASSIGN_WARNING = 2;
    const CONFIG_WARNING = 4;
    const FULL_SCHEMA_SCAN_WARNING = 8;
    const CONFIG_DEPRECATION_WARNING = 16;
    const NOT_A_TYPE = 32;

    const ALL = 63;

    static $enableWarnings = self::ALL;

    static $warned = [];

    static private $warningHandler;

    /**
     * Sets warning handler which (when set) will intercept all system warnings.
     * When not set, trigger_error() is used to notify about warnings.
     *
     * @param callable|null $warningHandler
     */
    public static function setWarningHandler(callable $warningHandler = null)
    {
        self::$warningHandler = $warningHandler;
    }

    /**
     * Suppress warning by id (has no effect when custom warning handler is set)
     *
     * Usage example:
     * Warning::suppress(Warning::NOT_A_TYPE)
     *
     * When passing true - suppresses all warnings.
     *
     * @param bool|int $suppress
     */
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

    /**
     * Re-enable previously suppressed warning by id
     *
     * Usage example:
     * Warning::suppress(Warning::NOT_A_TYPE)
     *
     * When passing true - re-enables all warnings.
     *
     * @param bool|int $enable
     */
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

    static function warnOnce($errorMessage, $warningId, $messageLevel = null)
    {
        if (self::$warningHandler) {
            $fn = self::$warningHandler;
            $fn($errorMessage, $warningId);
        } else if ((self::$enableWarnings & $warningId) > 0 && !isset(self::$warned[$warningId])) {
            self::$warned[$warningId] = true;
            trigger_error($errorMessage, $messageLevel ?: E_USER_WARNING);
        }
    }

    static function warn($errorMessage, $warningId, $messageLevel = null)
    {
        if (self::$warningHandler) {
            $fn = self::$warningHandler;
            $fn($errorMessage, $warningId);
        } else if ((self::$enableWarnings & $warningId) > 0) {
            trigger_error($errorMessage, $messageLevel ?: E_USER_WARNING);
        }
    }
}
