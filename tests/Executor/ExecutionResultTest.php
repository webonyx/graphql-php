<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use PHPUnit\Framework\TestCase;

class ExecutionResultTest extends TestCase
{
    public function testToArrayWithoutExtensions(): void
    {
        $executionResult = new ExecutionResult();

        self::assertSame([], $executionResult->toArray());
    }

    public function testToArrayExtensions(): void
    {
        $executionResult = new ExecutionResult(null, [], ['foo' => 'bar']);

        self::assertSame(['extensions' => ['foo' => 'bar']], $executionResult->toArray());

        $executionResult->extensions = ['bar' => 'foo'];

        self::assertSame(['extensions' => ['bar' => 'foo']], $executionResult->toArray());
    }

    public function testNoEmptyErrors(): void
    {
        $executionResult = new ExecutionResult(null, [new Error()]);
        $executionResult->setErrorsHandler(static fn (): array => []);

        self::assertSame([], $executionResult->toArray());
    }
}
