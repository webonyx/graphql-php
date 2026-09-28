<?php declare(strict_types=1);

namespace GraphQL\Tests\Error;

use GraphQL\Error\Warning;
use PHPUnit\Framework\TestCase;

final class WarningTest extends TestCase
{
    /** @var list<array{string, int, int|null}> */
    private array $warnings;

    protected function setUp(): void
    {
        self::resetWarningState();

        $this->warnings = [];
        Warning::setWarningHandler(function (string $errorMessage, int $warningId, ?int $messageLevel): void {
            $this->warnings[] = [$errorMessage, $warningId, $messageLevel];
        });
    }

    protected function tearDown(): void
    {
        self::resetWarningState();
    }

    public function testCallsCustomHandler(): void
    {
        Warning::warn('foo', Warning::WARNING_ASSIGN);
        Warning::warn('foo', Warning::WARNING_ASSIGN, \E_USER_NOTICE);

        self::assertSame([
            ['foo', Warning::WARNING_ASSIGN, \E_USER_WARNING],
            ['foo', Warning::WARNING_ASSIGN, \E_USER_NOTICE],
        ], $this->warnings);
    }

    public function testCallsCustomHandlerOnlyOnceForWarnOnce(): void
    {
        Warning::warnOnce('foo', Warning::WARNING_ASSIGN);
        Warning::warnOnce('bar', Warning::WARNING_ASSIGN);
        Warning::warnOnce('baz', Warning::WARNING_CONFIG);

        self::assertSame([
            ['foo', Warning::WARNING_ASSIGN, \E_USER_WARNING],
            ['baz', Warning::WARNING_CONFIG, \E_USER_WARNING],
        ], $this->warnings);
    }

    public function testSkipsCustomHandlerForSuppressedWarning(): void
    {
        Warning::suppress(Warning::WARNING_ASSIGN);
        Warning::warn('foo', Warning::WARNING_ASSIGN);
        Warning::warnOnce('foo', Warning::WARNING_ASSIGN);
        Warning::warn('bar', Warning::WARNING_CONFIG);

        self::assertSame([
            ['bar', Warning::WARNING_CONFIG, \E_USER_WARNING],
        ], $this->warnings);
    }

    public function testSkipsCustomHandlerWhenAllWarningsAreSuppressed(): void
    {
        Warning::suppress(true);
        Warning::warn('foo', Warning::WARNING_ASSIGN);

        self::assertSame([], $this->warnings);
    }

    public function testCallsCustomHandlerForReenabledWarning(): void
    {
        Warning::enable(false);
        Warning::enable(Warning::WARNING_ASSIGN);
        Warning::warn('foo', Warning::WARNING_ASSIGN);
        Warning::warn('bar', Warning::WARNING_CONFIG);

        self::assertSame([
            ['foo', Warning::WARNING_ASSIGN, \E_USER_WARNING],
        ], $this->warnings);
    }

    public function testTriggersErrorWithoutCustomHandler(): void
    {
        Warning::setWarningHandler(null);

        $errors = [];
        set_error_handler(static function (int $level, string $message) use (&$errors): bool {
            $errors[] = [$message, $level];

            return true;
        });
        try {
            Warning::warn('foo', Warning::WARNING_ASSIGN);
            Warning::suppress(Warning::WARNING_ASSIGN);
            Warning::warn('bar', Warning::WARNING_ASSIGN);
        } finally {
            restore_error_handler();
        }

        self::assertSame([['foo', \E_USER_WARNING]], $errors);
    }

    public static function resetWarningState(): void
    {
        Warning::setWarningHandler(null);
        Warning::enable(true);

        \Closure::bind(static function (): void {
            Warning::$warned = [];
        }, null, Warning::class)();
    }
}
