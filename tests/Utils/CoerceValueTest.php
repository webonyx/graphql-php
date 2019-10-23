<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Utils\Value;
use PHPUnit\Framework\TestCase;
use function acos;
use function log;
use function pow;

class CoerceValueTest extends TestCase
{
    /** @var EnumType */
    private $testEnum;

    /** @var InputObjectType */
    private $testInputObject;

    public function setUp()
    {
        $this->testEnum = new EnumType([
            'name'   => 'TestEnum',
            'values' => [
                'FOO' => 'InternalFoo',
                'BAR' => 123456789,
            ],
        ]);

        $this->testInputObject = new InputObjectType([
            'name'   => 'TestInputObject',
            'fields' => [
                'foo' => Type::nonNull(Type::int()),
                'bar' => Type::int(),
            ],
        ]);
    }

    /**
     * Describe: coerceValue
     */

    /**
     * Describe: for GraphQLString
     *
     * @see it('returns error for array input as string')
     */
    public function testCoercingAnArrayToGraphQLStringProducesAnError() : void
    {
        $result = Value::coerceValue([1, 2, 3], Type::string());
        $this->expectError(
            $result,
            'Expected type String; String cannot represent a non string value: [1,2,3]'
        );

        self::assertEquals(
            'String cannot represent a non string value: [1,2,3]',
            $result['errors'][0]->getPrevious()->getMessage()
        );
    }

    /**
     * Describe: for GraphQLID
     *
     * @see it('returns error for array input as ID')
     */
    public function testCoercingAnArrayToGraphQLIDProducesAnError() : void
    {
        $result = Value::coerceValue([1, 2, 3], Type::id());
        $this->expectError(
            $result,
            'Expected type ID; ID cannot represent value: [1,2,3]'
        );

        self::assertEquals(
            'ID cannot represent value: [1,2,3]',
            $result['errors'][0]->getPrevious()->getMessage()
        );
    }

    /**
     * Describe: for GraphQLInt
     */
    private function expectError($result, $expected)
    {
        self::assertInternalType('array', $result);
        self::assertInternalType('array', $result['errors']);
        self::assertCount(1, $result['errors']);
        self::assertEquals($expected, $result['errors'][0]->getMessage());
        self::assertEquals(Utils::undefined(), $result['value']);
    }

    /**
     * @see it('returns value for integer')
     */
    public function testIntReturnsNoErrorForIntInput() : void
    {
        $result = Value::coerceValue(1, Type::int());
        $this->expectValue($result, 1);
    }

    /**
     * @see it('returns error for numeric looking string')
     */
    public function testReturnsErrorForNumericLookingString()
    {
        $result = Value::coerceValue('1', Type::int());
        $this->expectError($result, 'Expected type Int; Int cannot represent non-integer value: 1');
    }

    private function expectValue($result, $expected)
    {
        self::assertInternalType('array', $result);
        self::assertEquals(null, $result['errors']);
        self::assertNotEquals(Utils::undefined(), $result['value']);
        self::assertEquals($expected, $result['value']);
    }

    /**
     * @see it('returns value for negative int input')
     */
    public function testIntReturnsNoErrorForNegativeIntInput() : void
    {
        $result = Value::coerceValue(-1, Type::int());
        $this->expectValue($result, -1);
    }

    /**
     * @see it('returns value for exponent input')
     */
    public function testIntReturnsNoErrorForExponentInput() : void
    {
        $result = Value::coerceValue(1e3, Type::int());
        $this->expectValue($result, 1000);
    }

    /**
     * @see it('returns null for null value')
     */
    public function testIntReturnsASingleErrorNull() : void
    {
        $result = Value::coerceValue(null, Type::int());
        $this->expectValue($result, null);
    }

    /**
     * @see it('returns a single error for empty string as value')
     */
    public function testIntReturnsASingleErrorForEmptyValue() : void
    {
        $result = Value::coerceValue('', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: (empty string)'
        );
    }

    /**
     * @see it('returns a single error for 2^32 input as int')
     */
    public function testReturnsASingleErrorFor2x32InputAsInt()
    {
        $result = Value::coerceValue(pow(2, 32), Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non 32-bit signed integer value: 4294967296'
        );
    }

    /**
     * @see it('returns error for float input as int')
     */
    public function testIntReturnsErrorForFloatInputAsInt() : void
    {
        $result = Value::coerceValue(1.5, Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: 1.5'
        );
    }

    /**
     * @see it('returns a single error for Infinity input as int')
     */
    public function testReturnsASingleErrorForInfinityInputAsInt()
    {
        $inf    = log(0);
        $result = Value::coerceValue($inf, Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non 32-bit signed integer value: -INF'
        );
    }

    public function testReturnsASingleErrorForNaNInputAsInt()
    {
        $nan    = acos(8);
        $result = Value::coerceValue($nan, Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: NAN'
        );
    }

    /**
     * @see it('returns a single error for string input')
     */
    public function testIntReturnsASingleErrorForCharInput() : void
    {
        $result = Value::coerceValue('a', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: a'
        );
    }

    /**
     * @see it('returns a single error for multi char input')
     */
    public function testIntReturnsASingleErrorForMultiCharInput() : void
    {
        $result = Value::coerceValue('meow', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: meow'
        );
    }

    // Describe: for GraphQLFloat

    /**
     * @see it('returns value for integer')
     */
    public function testFloatReturnsNoErrorForIntInput() : void
    {
        $result = Value::coerceValue(1, Type::float());
        $this->expectValue($result, 1);
    }

    /**
     * @see it('returns value for decimal')
     */
    public function testReturnsValueForDecimal()
    {
        $result = Value::coerceValue(1.1, Type::float());
        $this->expectValue($result, 1.1);
    }

    /**
     * @see it('returns value for exponent input')
     */
    public function testFloatReturnsNoErrorForExponentInput() : void
    {
        $result = Value::coerceValue(1e3, Type::float());
        $this->expectValue($result, 1000);
    }

    /**
     * @see it('returns error for numeric looking string')
     */
    public function testFloatReturnsErrorForNumericLookingString()
    {
        $result = Value::coerceValue('1', Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: 1'
        );
    }

    /**
     * @see it('returns null for null value')
     */
    public function testFloatReturnsASingleErrorNull() : void
    {
        $result = Value::coerceValue(null, Type::float());
        $this->expectValue($result, null);
    }

    /**
     * @see it('returns a single error for empty string input')
     */
    public function testFloatReturnsASingleErrorForEmptyValue() : void
    {
        $result = Value::coerceValue('', Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: (empty string)'
        );
    }

    /**
     * @see it('returns a single error for Infinity input')
     */
    public function testFloatReturnsASingleErrorForInfinityInput() : void
    {
        $inf    = log(0);
        $result = Value::coerceValue($inf, Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: -INF'
        );
    }

    public function testFloatReturnsASingleErrorForNaNInput() : void
    {
        $nan    = acos(8);
        $result = Value::coerceValue($nan, Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: NAN'
        );
    }

    // DESCRIBE: for GraphQLEnum

    /**
     * @see it('returns a single error for char input')
     */
    public function testFloatReturnsASingleErrorForCharInput() : void
    {
        $result = Value::coerceValue('a', Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: a'
        );
    }

    /**
     * @see it('returns a single error for multi char input')
     */
    public function testFloatReturnsASingleErrorForMultiCharInput() : void
    {
        $result = Value::coerceValue('meow', Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: meow'
        );
    }

    /**
     * @see it('returns no error for a known enum name')
     */
    public function testReturnsNoErrorForAKnownEnumName() : void
    {
        $fooResult = Value::coerceValue('FOO', $this->testEnum);
        $this->expectValue($fooResult, 'InternalFoo');

        $barResult = Value::coerceValue('BAR', $this->testEnum);
        $this->expectValue($barResult, 123456789);
    }

    // DESCRIBE: for GraphQLInputObject

    /**
     * @see it('results error for misspelled enum value')
     */
    public function testReturnsErrorForMisspelledEnumValue() : void
    {
        $result = Value::coerceValue('foo', $this->testEnum);
        $this->expectError($result, 'Expected type TestEnum; did you mean FOO?');
    }

    /**
     * @see it('results error for incorrect value type')
     */
    public function testReturnsErrorForIncorrectValueType() : void
    {
        $result1 = Value::coerceValue(123, $this->testEnum);
        $this->expectError($result1, 'Expected type TestEnum.');

        $result2 = Value::coerceValue(['field' => 'value'], $this->testEnum);
        $this->expectError($result2, 'Expected type TestEnum.');
    }

    /**
     * @see it('returns no error for a valid input')
     */
    public function testReturnsNoErrorForValidInput() : void
    {
        $result = Value::coerceValue(['foo' => 123], $this->testInputObject);
        $this->expectValue($result, ['foo' => 123]);
    }

    /**
     * @see it('returns no error for a non-object type')
     */
    public function testReturnsErrorForNonObjectType() : void
    {
        $result = Value::coerceValue(123, $this->testInputObject);
        $this->expectError($result, 'Expected type TestInputObject to be an object.');
    }

    public function testReturnsNoErrorForStdClassInput() : void
    {
        $result = Value::coerceValue((object) ['foo' => 123], $this->testInputObject);
        $this->expectValue($result, ['foo' => 123]);
    }

    /**
     * @see it('returns no error for an invalid field')
     */
    public function testReturnErrorForAnInvalidField() : void
    {
        $result = Value::coerceValue(['foo' => 'abc'], $this->testInputObject);
        $this->expectError(
            $result,
            'Expected type Int at value.foo; Int cannot represent non-integer value: abc'
        );
    }

    /**
     * @see it('returns multiple errors for multiple invalid fields')
     */
    public function testReturnsMultipleErrorsForMultipleInvalidFields() : void
    {
        $result = Value::coerceValue(['foo' => 'abc', 'bar' => 'def'], $this->testInputObject);
        self::assertEquals(
            [
                'Expected type Int at value.foo; Int cannot represent non-integer value: abc',
                'Expected type Int at value.bar; Int cannot represent non-integer value: def',
            ],
            $result['errors']
        );
    }

    /**
     * @see it('returns error for a missing required field')
     */
    public function testReturnsErrorForAMissingRequiredField() : void
    {
        $result = Value::coerceValue(['bar' => 123], $this->testInputObject);
        $this->expectError($result, 'Field value.foo of required type Int! was not provided.');
    }

    /**
     * @see it('returns error for an unknown field')
     */
    public function testReturnsErrorForAnUnknownField() : void
    {
        $result = Value::coerceValue(['foo' => 123, 'unknownField' => 123], $this->testInputObject);
        $this->expectError($result, 'Field "unknownField" is not defined by type TestInputObject.');
    }

    /**
     * @see it('returns error for a misspelled field')
     */
    public function testReturnsErrorForAMisspelledField() : void
    {
        $result = Value::coerceValue(['foo' => 123, 'bart' => 123], $this->testInputObject);
        $this->expectError($result, 'Field "bart" is not defined by type TestInputObject; did you mean bar?');
    }
}
