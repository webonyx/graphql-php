<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Error\Error;
use GraphQL\Executor\Values;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Utils\Value;
use PHPUnit\Framework\TestCase;

class CoerceValueTest extends TestCase
{
    private $testEnum;
    private $testInputObject;

    public function setUp()
    {
        $this->testEnum = new EnumType([
            'name' => 'TestEnum',
            'values' => [
                'FOO' => 'InternalFoo',
                'BAR' => 123456789,
            ],
        ]);

        $this->testInputObject = new InputObjectType([
            'name' => 'TestInputObject',
            'fields' => [
                'foo' => Type::nonNull(Type::int()),
                'bar' => Type::int(),
            ],
        ]);
    }

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

    // DESCRIBE: for GraphQLEnum

    /**
     * @it returns no error for a known enum name
     */
    public function testReturnsNoErrorForAKnownEnumName()
    {
        $fooResult = Value::coerceValue('FOO', $this->testEnum);
        $this->expectNoErrors($fooResult);
        $this->assertEquals('InternalFoo', $fooResult['value']);

        $barResult = Value::coerceValue('BAR', $this->testEnum);
        $this->expectNoErrors($barResult);
        $this->assertEquals(123456789, $barResult['value']);
    }

    /**
     * @it results error for misspelled enum value
     */
    public function testReturnsErrorForMisspelledEnumValue()
    {
        $result = Value::coerceValue('foo', $this->testEnum);
        $this->expectError($result, 'Expected type TestEnum; did you mean FOO?');
    }

    /**
     * @it results error for incorrect value type
     */
    public function testReturnsErrorForIncorrectValueType()
    {
        $result1 = Value::coerceValue(123, $this->testEnum);
        $this->expectError($result1, 'Expected type TestEnum.');

        $result2 = Value::coerceValue(['field' => 'value'], $this->testEnum);
        $this->expectError($result2, 'Expected type TestEnum.');
    }

    // DESCRIBE: for GraphQLInputObject

    /**
     * @it returns no error for a valid input
     */
    public function testReturnsNoErrorForValidInput()
    {
        $result = Value::coerceValue(['foo' => 123], $this->testInputObject);
        $this->expectNoErrors($result);
        $this->assertEquals(['foo' => 123], $result['value']);
    }

    /**
     * @it returns no error for a non-object type
     */
    public function testReturnsErrorForNonObjectType()
    {
        $result = Value::coerceValue(123, $this->testInputObject);
        $this->expectError($result, 'Expected type TestInputObject to be an object.');
    }

    /**
     * @it returns no error for an invalid field
     */
    public function testReturnErrorForAnInvalidField()
    {
        $result = Value::coerceValue(['foo' => 'abc'], $this->testInputObject);
        $this->expectError($result, 'Expected type Int at value.foo; Int cannot represent non 32-bit signed integer value: abc');
    }

    /**
     * @it returns multiple errors for multiple invalid fields
     */
    public function testReturnsMultipleErrorsForMultipleInvalidFields()
    {
        $result = Value::coerceValue(['foo' => 'abc', 'bar' => 'def'], $this->testInputObject);
        $this->assertEquals([
            'Expected type Int at value.foo; Int cannot represent non 32-bit signed integer value: abc',
            'Expected type Int at value.bar; Int cannot represent non 32-bit signed integer value: def',
        ], $result['errors']);
    }

    /**
     * @it returns error for a missing required field
     */
    public function testReturnsErrorForAMissingRequiredField()
    {
        $result = Value::coerceValue(['bar' => 123], $this->testInputObject);
        $this->expectError($result, 'Field value.foo of required type Int! was not provided.');
    }

    /**
     * @it returns error for an unknown field
     */
    public function testReturnsErrorForAnUnknownField()
    {
        $result = Value::coerceValue(['foo' => 123, 'unknownField' => 123], $this->testInputObject);
        $this->expectError($result, 'Field "unknownField" is not defined by type TestInputObject.');
    }

    /**
     * @it returns error for a misspelled field
     */
    public function testReturnsErrorForAMisspelledField()
    {
        $result = Value::coerceValue(['foo' => 123, 'bart' => 123], $this->testInputObject);
        $this->expectError($result, 'Field "bart" is not defined by type TestInputObject; did you mean bar?');
    }

    private function expectNoErrors($result)
    {
        $this->assertInternalType('array', $result);
        $this->assertNull($result['errors']);
        $this->assertNotEquals(Utils::undefined(), $result['value']);
    }


    private function expectError($result, $expected) {
        $this->assertInternalType('array', $result);
        $this->assertInternalType('array', $result['errors']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals($expected, $result['errors'][0]->getMessage());
        $this->assertEquals(Utils::undefined(), $result['value']);
    }
}
