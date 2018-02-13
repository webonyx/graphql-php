<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Executor\Values;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Value;

class CoerceValueTest extends \PHPUnit_Framework_TestCase
{
    // Describe: coerceValue

    /**
     * @it coercing an array to GraphQLString produces an error
     */
    public function testCoercingAnArrayToGraphQLStringProducesAnError()
    {
        $result = Value::coerceValue([1, 2, 3], Type::string());
        $this->expectError(
            $result,
            'Expected type String; String cannot represent an array value: [1,2,3]'
        );

        $this->assertEquals(
            'String cannot represent an array value: [1,2,3]',
            $result['errors'][0]->getPrevious()->getMessage()
        );
    }

    // Describe: for GraphQLInt

    /**
     * @it returns no error for int input
     */
    public function testIntReturnsNoErrorForIntInput()
    {
        $result = Value::coerceValue('1', Type::int());
        $this->expectNoErrors($result);
    }

    /**
     * @it returns no error for negative int input
     */
    public function testIntReturnsNoErrorForNegativeIntInput()
    {
        $result = Value::coerceValue('-1', Type::int());
        $this->expectNoErrors($result);
    }

    /**
     * @it returns no error for exponent input
     */
    public function testIntReturnsNoErrorForExponentInput()
    {
        $result = Value::coerceValue('1e3', Type::int());
        $this->expectNoErrors($result);
    }

    /**
     * @it returns no error for null
     */
    public function testIntReturnsASingleErrorNull()
    {
        $result = Value::coerceValue(null, Type::int());
        $this->expectNoErrors($result);
    }

    /**
     * @it returns a single error for empty value
     */
    public function testIntReturnsASingleErrorForEmptyValue()
    {
        $result = Value::coerceValue('', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non 32-bit signed integer value: (empty string)'
        );
    }

    /**
     * @it returns error for float input as int
     */
    public function testIntReturnsErrorForFloatInputAsInt()
    {
        $result = Value::coerceValue('1.5', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: 1.5'
        );
    }

    /**
     * @it returns a single error for char input
     */
    public function testIntReturnsASingleErrorForCharInput()
    {
        $result = Value::coerceValue('a', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non 32-bit signed integer value: a'
        );
    }

    /**
     * @it returns a single error for multi char input
     */
    public function testIntReturnsASingleErrorForMultiCharInput()
    {
        $result = Value::coerceValue('meow', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non 32-bit signed integer value: meow'
        );
    }

    // Describe: for GraphQLFloat

    /**
     * @it returns no error for int input
     */
    public function testFloatReturnsNoErrorForIntInput()
    {
        $result = Value::coerceValue('1', Type::float());
        $this->expectNoErrors($result);
    }

    /**
     * @it returns no error for exponent input
     */
    public function testFloatReturnsNoErrorForExponentInput()
    {
        $result = Value::coerceValue('1e3', Type::float());
        $this->expectNoErrors($result);
    }

    /**
     * @it returns no error for float input
     */
    public function testFloatReturnsNoErrorForFloatInput()
    {
        $result = Value::coerceValue('1.5', Type::float());
        $this->expectNoErrors($result);
    }

    /**
     * @it returns no error for null
     */
    public function testFloatReturnsASingleErrorNull()
    {
        $result = Value::coerceValue(null, Type::float());
        $this->expectNoErrors($result);
    }

    /**
     * @it returns a single error for empty value
     */
    public function testFloatReturnsASingleErrorForEmptyValue()
    {
        $result = Value::coerceValue('', Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: (empty string)'
        );
    }

    /**
     * @it returns a single error for char input
     */
    public function testFloatReturnsASingleErrorForCharInput()
    {
        $result = Value::coerceValue('a', Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: a'
        );
    }

    /**
     * @it returns a single error for multi char input
     */
    public function testFloatReturnsASingleErrorForMultiCharInput()
    {
        $result = Value::coerceValue('meow', Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: meow'
        );
    }

    private function expectNoErrors($result)
    {
        $this->assertInternalType('array', $result);
        $this->assertNull($result['errors']);
    }

    private function expectError($result, $expected) {
        $this->assertInternalType('array', $result);
        $this->assertInternalType('array', $result['errors']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals($expected, $result['errors'][0]->getMessage());
    }
}
