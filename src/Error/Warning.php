<?php

declare(strict_types=1);

namespace GraphQL\Error;

use GraphQL\Exception\InvalidArgument;
use function is_int;
use function trigger_error;
use const E_USER_WARNING;

/**
 * Encapsulates warnings produced by the library.
 *
 * Warnings can be suppressed (individually or all) if required.
 * Also it is possible to override warning handler (which is **trigger_error()** by default)
 */
final class Warning
{
    public const WARNING_ASSIGN             = 2;
    public const WARNING_CONFIG             = 4;
    public const WARNING_FULL_SCHEMA_SCAN   = 8;
    public const WARNING_CONFIG_DEPRECATION = 16;
    public const WARNING_NOT_A_TYPE         = 32;
    public const ALL                        = 63;

    /** @var int */
    private static $enableWarnings = self::ALL;

    /** @var mixed[] */
    private static $warned = [];

    /** @var callable|null */
    private static $warningHandler;

    /**
     * Sets warning handler which can intercept all system warnings.
     * When not set, trigger_error() is used to notify about warnings.
     *
     * @api
     */
    public static function setWarningHandler(?callable $warningHandler = null) : void
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
     * @param bool|int $suppress
     *
     * @api
     */
    public static function suppress($suppress = true) : void
    {
        if ($suppress === true) {
            self::$enableWarnings = 0;
        } elseif ($suppress === false) {
            self::$enableWarnings = self::ALL;
        } elseif (is_int($suppress)) {
            self::$enableWarnings &= ~$suppress;
        } else {
            throw InvalidArgument::fromExpectedTypeAndArgument('bool|int', $suppress);
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
     * @param bool|int $enable
     *
     * @api
     */
    public static function enable($enable = true) : void
    {
        if ($enable === true) {
            self::$enableWarnings = self::ALL;
        } elseif ($enable === false) {
            self::$enableWarnings = 0;
        } elseif (is_int($enable)) {
            self::$enableWarnings |= $enable;
        } else {
            throw InvalidArgument::fromExpectedTypeAndArgument('bool|int', $enable);
        }
    }

    public static function warnOnce(string $errorMessage, int $warningId, ?int $messageLevel = null) : void
    {
        $messageLevel = $messageLevel ?? E_USER_WARNING;

        if (self::$warningHandler !== null) {
            $fn = self::$warningHandler;
            $fn($errorMessage, $warningId, $messageLevel);
        } elseif ((self::$enableWarnings & $warningId) > 0 && ! isset(self::$warned[$warningId])) {
            self::$warned[$warningId] = true;
            trigger_error($errorMessage, $messageLevel);
        }
    }

    public static function warn(string $errorMessage, int $warningId, ?int $messageLevel = null) : void
    {
        $messageLevel = $messageLevel ?? E_USER_WARNING;

        if (self::$warningHandler !== null) {
            $fn = self::$warningHandler;
            $fn($errorMessage, $warningId, $messageLevel);
        } elseif ((self::$enableWarnings & $warningId) > 0) {
            trigger_error($errorMessage, $messageLevel);
        }
    }
}
