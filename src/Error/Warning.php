<?php
namespace GraphQL\Error;

/**
 * Encapsulates warnings produced by the library.
 *
 * Warnings can be suppressed (individually or all) if required.
 * Also it is possible to override warning handler (which is **trigger_error()** by default)
 */
final class Warning
{
    const WARNING_NAME = 1;
    const WARNING_ASSIGN = 2;
    const WARNING_CONFIG = 4;
    const WARNING_FULL_SCHEMA_SCAN = 8;
    const WARNING_CONFIG_DEPRECATION = 16;
    const WARNING_NOT_A_TYPE = 32;
    const ALL = 63;

    static $enableWarnings = self::ALL;

    static $warned = [];

    static private $warningHandler;

    /**
     * Sets warning handler which can intercept all system warnings.
     * When not set, trigger_error() is used to notify about warnings.
     *
     * @api
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
     * Warning::suppress(Warning::WARNING_NOT_A_TYPE)
     *
     * When passing true - suppresses all warnings.
     *
     * @api
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
     * Warning::suppress(Warning::WARNING_NOT_A_TYPE)
     *
     * When passing true - re-enables all warnings.
     *
     * @api
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
