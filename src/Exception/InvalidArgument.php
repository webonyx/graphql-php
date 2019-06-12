<?php

declare(strict_types=1);

namespace GraphQL\Exception;

use InvalidArgumentException;
use function gettype;
use function sprintf;

final class InvalidArgument extends InvalidArgumentException
{
    /**
     * @param mixed $argument
     */
    public static function fromExpectedTypeAndArgument(string $expectedType, $argument) : self
    {
        return new self(sprintf('Expected type "%s", got "%s"', $expectedType, gettype($argument)));
    }
}
