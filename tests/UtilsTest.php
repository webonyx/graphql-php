<?php
namespace GraphQL\Tests;

use GraphQL\Utils\Utils;

class UtilsTest extends \PHPUnit_Framework_TestCase
{
    public function testAssignThrowsExceptionOnMissingRequiredKey()
    {
        $object = new \stdClass();
        $object->requiredKey = 'value';

        try {
            Utils::assign($object, [], ['requiredKey']);
            $this->fail('Expected exception not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals(
                "Key requiredKey is expected to be set and not to be null",
                $e->getMessage());
        }
    }
}
