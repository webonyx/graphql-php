<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Utils\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

class UtilsTest extends TestCase
{
    public function testAssignThrowsExceptionOnMissingRequiredKey() : void
    {
        $object              = new stdClass();
        $object->requiredKey = 'value';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key requiredKey is expected to be set and not to be null');
        Utils::assign($object, [], ['requiredKey']);
    }
}
