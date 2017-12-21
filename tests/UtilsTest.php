<?php
namespace GraphQL\Tests;

use GraphQL\Utils\Utils;

class UtilsTest extends \PHPUnit_Framework_TestCase
{
    public function testAssignThrowsExceptionOnMissingRequiredKey()
    {
        $object = new \stdClass();
        $object->requiredKey = 'value';

        $this->setExpectedException(\InvalidArgumentException::class, 'Key requiredKey is expected to be set and not to be null');
        Utils::assign($object, [], ['requiredKey']);
    }
}
