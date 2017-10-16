<?php
namespace GraphQL\Tests\Utils;


use GraphQL\Executor\Values;
use GraphQL\Type\Definition\Type;

class IsValidPHPValueTest extends \PHPUnit_Framework_TestCase
{
    public function testValidIntValue()
    {
        // returns no error for positive int value
        $result = Values::isValidPHPValue(1, Type::int());
        $this->expectNoErrors($result);

        // returns no error for negative int value
        $result = Values::isValidPHPValue(-1, Type::int());
        $this->expectNoErrors($result);

        // returns no error for null value
        $result = Values::isValidPHPValue(null, Type::int());
        $this->expectNoErrors($result);

        // returns a single error for positive int string value
        $result = Values::isValidPHPValue('1', Type::int());
        $this->expectErrorResult($result, 1);

        // returns a single error for negative int string value
        $result = Values::isValidPHPValue('-1', Type::int());
        $this->expectErrorResult($result, 1);

        // returns errors for exponential int string value
        $result = Values::isValidPHPValue('1e3', Type::int());
        $this->expectErrorResult($result, 1);
        $result = Values::isValidPHPValue('0e3', Type::int());
        $this->expectErrorResult($result, 1);

        // returns a single error for empty value
        $result = Values::isValidPHPValue('', Type::int());
        $this->expectErrorResult($result, 1);

        // returns error for float value
        $result = Values::isValidPHPValue(1.5, Type::int());
        $this->expectErrorResult($result, 1);
        $result = Values::isValidPHPValue(1e3, Type::int());
        $this->expectErrorResult($result, 1);

        // returns error for float string value
        $result = Values::isValidPHPValue('1.5', Type::int());
        $this->expectErrorResult($result, 1);

        // returns a single error for char input
        $result = Values::isValidPHPValue('a', Type::int());
        $this->expectErrorResult($result, 1);

        // returns a single error for char input
        $result = Values::isValidPHPValue('meow', Type::int());
        $this->expectErrorResult($result, 1);
    }

    public function testValidFloatValue()
    {
        // returns no error for positive float value
        $result = Values::isValidPHPValue(1.2, Type::float());
        $this->expectNoErrors($result);

        // returns no error for exponential float value
        $result = Values::isValidPHPValue(1e3, Type::float());
        $this->expectNoErrors($result);

        // returns no error for negative float value
        $result = Values::isValidPHPValue(-1.2, Type::float());
        $this->expectNoErrors($result);

        // returns no error for a positive int value
        $result = Values::isValidPHPValue(1, Type::float());
        $this->expectNoErrors($result);

        // returns no errors for a negative int value
        $result = Values::isValidPHPValue(-1, Type::float());
        $this->expectNoErrors($result);

        // returns no error for null value:
        $result = Values::isValidPHPValue(null, Type::float());
        $this->expectNoErrors($result);

        // returns error for positive float string value
        $result = Values::isValidPHPValue('1.2', Type::float());
        $this->expectErrorResult($result, 1);

        // returns error for negative float string value
        $result = Values::isValidPHPValue('-1.2', Type::float());
        $this->expectErrorResult($result, 1);

        // returns error for a positive int string value
        $result = Values::isValidPHPValue('1', Type::float());
        $this->expectErrorResult($result, 1);

        // returns errors for a negative int string value
        $result = Values::isValidPHPValue('-1', Type::float());
        $this->expectErrorResult($result, 1);

        // returns error for exponent input
        $result = Values::isValidPHPValue('1e3', Type::float());
        $this->expectErrorResult($result, 1);
        $result = Values::isValidPHPValue('0e3', Type::float());
        $this->expectErrorResult($result, 1);

        // returns a single error for empty value
        $result = Values::isValidPHPValue('', Type::float());
        $this->expectErrorResult($result, 1);

        // returns a single error for char input
        $result = Values::isValidPHPValue('a', Type::float());
        $this->expectErrorResult($result, 1);

        // returns a single error for char input
        $result = Values::isValidPHPValue('meow', Type::float());
        $this->expectErrorResult($result, 1);
    }

    private function expectNoErrors($result)
    {
        $this->assertInternalType('array', $result);
        $this->assertEquals([], $result);
    }

    private function expectErrorResult($result, $size) {
        $this->assertInternalType('array', $result);
        $this->assertEquals($size, count($result));
    }
}
