<?php

declare(strict_types=1);

namespace GraphQL\Tests\Exception;

use GraphQL\Exception\InvalidArgument;
use PHPUnit\Framework\TestCase;

final class InvalidArgumentTest extends TestCase
{
    public function testFromExpectedTypeAndArgument() : void
    {
        $exception = InvalidArgument::fromExpectedTypeAndArgument('bool|int', 'stringValue');

        self::assertSame('Expected type "bool|int", got "string"', $exception->getMessage());
    }
}
