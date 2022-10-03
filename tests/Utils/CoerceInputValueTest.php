<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use function acos;

use GraphQL\Error\CoercionError;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Value;

use function log;

use PHPUnit\Framework\TestCase;

use function pow;

use stdClass;

/**
 * @phpstan-import-type InputPath from CoercionError
 */
class CoerceInputValueTest extends TestCase
{
    private NonNull $testNonNull;

    private CustomScalarType $testScalar;

    private EnumType $testEnum;

    private InputObjectType $testInputObject;

    public function setUp(): void
    {
        $this->testNonNull = Type::nonNull(Type::int());

        $this->testScalar = new CustomScalarType([
            'name' => 'TestScalar',
            'parseValue' => function ($input) {
                if (isset($input['error'])) {
                    throw new \Exception($input['error']);
                }

                return $input['value'];
            },
            'parseLiteral' => fn () => null,
        ]);

        $this->testEnum = new EnumType([
            'name' => 'TestEnum',
            'values' => [
                'FOO' => 'InternalFoo',
                'BAR' => 123456789,
            ],
        ]);

        $nestedObject = new InputObjectType([
            'name' => 'TestInputObject',
            'fields' => [
                'foobar' => Type::nonNull(Type::int()),
            ],
        ]);

        $this->testInputObject = new InputObjectType([
            'name' => 'TestInputObject',
            'fields' => [
                'foo' => Type::nonNull(Type::int()),
                'bar' => Type::int(),
                'nested' => $nestedObject,
            ],
        ]);
    }

    /**
     * @see describe('coerceInputValue', () => {
     * @see describe('for GraphQLNonNull', () => {
     * @see it('returns no error for non-null value', () => {
     */
    public function testReturnsNoErrorForNonNullValue(): void
    {
        $result = Value::coerceInputValue(1, $this->testNonNull);
        $this->expectGraphQLValue($result, 1);
    }

    /**
     * @see it('returns an error for undefined value', () => {
     * @see it('returns an error for null value', () => {
     */
    public function testReturnsNoErrorForNullValue(): void
    {
        $result = Value::coerceInputValue(null, $this->testNonNull);
        $this->expectGraphQLError($result, [CoercionError::make('Expected non-nullable type "Int!" not to be null.', null, null)]);
    }

    /**
     * @see describe('for GraphQLScalar', () => {
     * @see it('returns no error for valid input', () => {
     */
    public function testReturnsNoErrorForValidInput(): void
    {
        $result = Value::coerceInputValue(['value' => 1], $this->testScalar);
        $this->expectGraphQLValue($result, 1);
    }

    /**
     * @see it('returns no error for null result', () => {
     * @see it('returns no error for NaN result', () => {
     * @see it('returns an error for undefined result', () => {
     */
    public function testReturnsNoErrorForNullResult(): void
    {
        $result = Value::coerceInputValue(['value' => null], $this->testScalar);
        $this->expectGraphQLValue($result, null);
    }

    /**
     * @see it('it('returns a thrown error', () => {', () => {
     */
    public function testReturnsAThrownError(): void
    {
        $result = Value::coerceInputValue(['error' => 'Some error message'], $this->testScalar);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Expected type "TestScalar".',
            null,
            ['error' => 'Some error message'],
            new \Exception('Some error message'),
        )]);
    }

    /**
     * @see describe('for GraphQLEnum', () => {
     * @see it('returns no error for a known enum name', () => {
     */
    public function testReturnsNoErrorForAKnownEnumName(): void
    {
        $fooResult = Value::coerceInputValue('FOO', $this->testEnum);
        $this->expectGraphQLValue($fooResult, 'InternalFoo');

        $barResult = Value::coerceInputValue('BAR', $this->testEnum);
        $this->expectGraphQLValue($barResult, 123456789);
    }

    /**
     * @see it('returns an error for misspelled enum value', () => {
     */
    public function testReturnsAnErrorForMisspelledEnumValue(): void
    {
        $result = Value::coerceInputValue('foo', $this->testEnum);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Value "foo" does not exist in "TestEnum" enum. Did you mean the enum value "FOO"?',
            null,
            'foo',
        )]);
    }

    /**
     * @see it('returns an error for incorrect value type', () => {
     */
    public function testReturnsErrorForIncorrectValueType(): void
    {
        $result1 = Value::coerceInputValue(123, $this->testEnum);
        $this->expectGraphQLError($result1, [CoercionError::make(
            'Enum "TestEnum" cannot represent non-string value: 123.',
            null,
            123
        )]);

        $result2 = Value::coerceInputValue(['field' => 'value'], $this->testEnum);
        $this->expectGraphQLError($result2, [CoercionError::make(
            // Differs from graphql-js due to JSON serialization
            'Enum "TestEnum" cannot represent non-string value: {"field":"value"}.',
            null,
            ['field' => 'value'],
        )]);
    }

    /**
     * @see describe('for GraphQLInputObject', () => {
     * @see it('returns no error for a valid input')
     *
     * @param mixed $input
     *
     * @dataProvider validInputObjects
     */
    public function testReturnsNoErrorForAValidInput($input): void
    {
        $result = Value::coerceInputValue($input, $this->testInputObject);
        $this->expectGraphQLValue($result, ['foo' => 123]);
    }

    /**
     * @return iterable<int, array{mixed}>
     */
    public function validInputObjects(): iterable
    {
        yield [['foo' => 123]];
        yield [(object) ['foo' => 123]];
    }

    /**
     * @see it('returns an error for a non-object type', () => {
     */
    public function testReturnsAnErrorForANonObjectType(): void
    {
        $result = Value::coerceInputValue(123, $this->testInputObject);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Expected type "TestInputObject" to be an object.',
            null,
            123
        )]);
    }

    /**
     * @see it('returns an error for an invalid field', () => {
     */
    public function testReturnsAnErrorForAnInvalidField(): void
    {
        $notInt = new stdClass();
        $result = Value::coerceInputValue(['foo' => $notInt], $this->testInputObject);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Int cannot represent non-integer value: instance of stdClass',
            ['foo'],
            $notInt
        )]);
    }

    /**
     * @see it('returns multiple errors for multiple invalid fields', () => {
     */
    public function testReturnsMultipleErrorsForMultipleInvalidFields(): void
    {
        $result = Value::coerceInputValue(['foo' => 'abc', 'bar' => 'def'], $this->testInputObject);
        $this->expectGraphQLError($result, [
            CoercionError::make(
                'Int cannot represent non-integer value: abc',
                ['foo'],
                'abc'
            ),
            CoercionError::make(
                'Int cannot represent non-integer value: def',
                ['bar'],
                'def'
            ),
        ]);
    }

    /**
     * @see it('returns error for a missing required field', () => {
     */
    public function testReturnsErrorForAMissingRequiredField(): void
    {
        $result = Value::coerceInputValue(['bar' => 123], $this->testInputObject);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Field "foo" of required type "Int!" was not provided.',
            null,
            ['bar' => 123]
        )]);
    }

    /**
     * @see it('returns error for an unknown field', () => {
     */
    public function testReturnsErrorForAnUnknownField(): void
    {
        $result = Value::coerceInputValue(['foo' => 123, 'unknownField' => 123], $this->testInputObject);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Field "unknownField" is not defined by type "TestInputObject".',
            null,
            ['foo' => 123, 'unknownField' => 123],
        )]);
    }

    /**
     * @see it('returns error for a misspelled field', () => {
     */
    public function testReturnsErrorForAMisspelledField(): void
    {
        $result = Value::coerceInputValue(['foo' => 123, 'bart' => 123], $this->testInputObject);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Field "bart" is not defined by type "TestInputObject". Did you mean "bar"?',
            null,
            ['foo' => 123, 'bart' => 123],
        )]);
    }

    /**
     * @see describe('for GraphQLInputObject with default value', () => {
     * @see it('returns no errors for valid input value', () => {
     */
    public function itReturnsNoErrorsForValidInputValue(): void
    {

    }






    /**
     * Describe: for GraphQLString.
     *
     * @see it('returns error for array input as string')
     */
    public function testCoercingAnArrayToGraphQLStringProducesAnError(): void
    {
        $result = Value::coerceInputValue([1, 2, 3], Type::string());
        $this->expectGraphQLError(
            $result,
            'Expected type String; String cannot represent a non string value: [1,2,3]'
        );

        $errors = $result['errors'];
        self::assertIsArray($errors);
    }

    /**
     * Describe: for GraphQLID.
     *
     * @see it('returns error for array input as ID')
     */
    public function testCoercingAnArrayToGraphQLIDProducesAnError(): void
    {
        $result = Value::coerceInputValue([1, 2, 3], Type::id());
        $this->expectGraphQLError(
            $result,
            'Expected type ID; ID cannot represent a non-string and non-integer value: [1,2,3]'
        );
    }

    /**
     * @param mixed $result returned result
     * @param list<CoercionError> $coercionErrors
     */
    private function expectGraphQLError($result, array $coercionErrors): void
    {
        self::assertIsArray($result);

        $errors = $result['errors'];
        self::assertIsArray($errors);
        self::assertCount(count($coercionErrors), $errors);

        foreach ($errors as $i => $error) {
            $expected = $coercionErrors[$i];
            self::assertSame($expected->getMessage(), $error->getMessage());
            self::assertSame($expected->inputPath, $error->inputPath);
            self::assertSame($expected->invalidValue, $error->invalidValue);
        }

        self::assertNull($result['value']);
    }

    /**
     * @see it('returns value for integer')
     */
    public function testIntReturnsNoErrorForIntInput(): void
    {
        $result = Value::coerceInputValue(1, Type::int());
        $this->expectGraphQLValue($result, 1);
    }

    /**
     * @see it('returns error for numeric looking string')
     */
    public function testReturnsErrorForNumericLookingString(): void
    {
        $result = Value::coerceInputValue('1', Type::int());
        $this->expectGraphQLError($result, 'Expected type Int; Int cannot represent non-integer value: 1');
    }

    /**
     * @param mixed $result
     * @param mixed $expected
     */
    private function expectGraphQLValue($result, $expected): void
    {
        self::assertIsArray($result);
        self::assertNull($result['errors']);
        self::assertSame($expected, $result['value']);
    }

    /**
     * @see it('returns value for negative int input')
     */
    public function testIntReturnsNoErrorForNegativeIntInput(): void
    {
        $result = Value::coerceInputValue(-1, Type::int());
        $this->expectGraphQLValue($result, -1);
    }

    /**
     * @see it('returns value for exponent input')
     */
    public function testIntReturnsNoErrorForExponentInput(): void
    {
        $result = Value::coerceInputValue(1e3, Type::int());
        $this->expectGraphQLValue($result, 1000);
    }

    /**
     * @see it('returns null for null value')
     */
    public function testIntReturnsASingleErrorNull(): void
    {
        $result = Value::coerceInputValue(null, Type::int());
        $this->expectGraphQLValue($result, null);
    }

    /**
     * @see it('returns a single error for empty string as value')
     */
    public function testIntReturnsASingleErrorForEmptyValue(): void
    {
        $result = Value::coerceInputValue('', Type::int());
        $this->expectGraphQLError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: (empty string)'
        );
    }

    /**
     * @see it('returns a single error for 2^32 input as int')
     */
    public function testReturnsASingleErrorFor2x32InputAsInt(): void
    {
        $result = Value::coerceInputValue(pow(2, 32), Type::int());
        $this->expectGraphQLError(
            $result,
            'Expected type Int; Int cannot represent non 32-bit signed integer value: 4294967296'
        );
    }

    /**
     * @see it('returns error for float input as int')
     */
    public function testIntReturnsErrorForFloatInputAsInt(): void
    {
        $result = Value::coerceInputValue(1.5, Type::int());
        $this->expectGraphQLError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: 1.5'
        );
    }

    /**
     * @see it('returns a single error for Infinity input as int')
     */
    public function testReturnsASingleErrorForInfinityInputAsInt(): void
    {
        $inf = log(0);
        $result = Value::coerceInputValue($inf, Type::int());
        $this->expectGraphQLError(
            $result,
            'Expected type Int; Int cannot represent non 32-bit signed integer value: -INF'
        );
    }

    public function testReturnsASingleErrorForNaNInputAsInt(): void
    {
        $nan = acos(8);
        $result = Value::coerceInputValue($nan, Type::int());
        $this->expectGraphQLError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: NAN'
        );
    }

    /**
     * @see it('returns a single error for string input')
     */
    public function testIntReturnsASingleErrorForCharInput(): void
    {
        $result = Value::coerceInputValue('a', Type::int());
        $this->expectGraphQLError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: a'
        );
    }

    /**
     * @see it('returns a single error for multi char input')
     */
    public function testIntReturnsASingleErrorForMultiCharInput(): void
    {
        $result = Value::coerceInputValue('meow', Type::int());
        $this->expectGraphQLError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: meow'
        );
    }

    // Describe: for GraphQLFloat

    /**
     * @see it('returns value for integer')
     */
    public function testFloatReturnsNoErrorForIntInput(): void
    {
        $result = Value::coerceInputValue(1, Type::float());
        $this->expectGraphQLValue($result, 1.0);
    }

    /**
     * @see it('returns value for decimal')
     */
    public function testReturnsValueForDecimal(): void
    {
        $result = Value::coerceInputValue(1.1, Type::float());
        $this->expectGraphQLValue($result, 1.1);
    }

    /**
     * @see it('returns value for exponent input')
     */
    public function testFloatReturnsNoErrorForExponentInput(): void
    {
        $result = Value::coerceInputValue(1e3, Type::float());
        $this->expectGraphQLValue($result, 1000.0);
    }

    /**
     * @see it('returns error for numeric looking string')
     */
    public function testFloatReturnsErrorForNumericLookingString(): void
    {
        $result = Value::coerceInputValue('1', Type::float());
        $this->expectGraphQLError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: 1'
        );
    }

    /**
     * @see it('returns null for null value')
     */
    public function testFloatReturnsASingleErrorNull(): void
    {
        $result = Value::coerceInputValue(null, Type::float());
        $this->expectGraphQLValue($result, null);
    }

    /**
     * @see it('returns a single error for empty string input')
     */
    public function testFloatReturnsASingleErrorForEmptyValue(): void
    {
        $result = Value::coerceInputValue('', Type::float());
        $this->expectGraphQLError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: (empty string)'
        );
    }

    /**
     * @see it('returns a single error for Infinity input')
     */
    public function testFloatReturnsASingleErrorForInfinityInput(): void
    {
        $inf = log(0);
        $result = Value::coerceInputValue($inf, Type::float());
        $this->expectGraphQLError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: -INF'
        );
    }

    public function testFloatReturnsASingleErrorForNaNInput(): void
    {
        $nan = acos(8);
        $result = Value::coerceInputValue($nan, Type::float());
        $this->expectGraphQLError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: NAN'
        );
    }

    // DESCRIBE: for GraphQLEnum

    /**
     * @see it('returns a single error for char input')
     */
    public function testFloatReturnsASingleErrorForCharInput(): void
    {
        $result = Value::coerceInputValue('a', Type::float());
        $this->expectGraphQLError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: a'
        );
    }

    /**
     * @see it('returns a single error for multi char input')
     */
    public function testFloatReturnsASingleErrorForMultiCharInput(): void
    {
        $result = Value::coerceInputValue('meow', Type::float());
        $this->expectGraphQLError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: meow'
        );
    }

    // DESCRIBE: for GraphQLInputObject

    /**
     * @return iterable<int, array{0: mixed, 1: string, 2: InputPath|null}>
     */
    public function invalidInputObjects(): iterable
    {
        yield [
            ['foo' => 1234, 'nested' => ['foobar' => null]],
            'Expected non-nullable type Int! not to be null at value.nested.foobar.',
            ['nested', 'foobar'],
        ];
        yield [
            ['foo' => null, 'bar' => 1234],
            'Expected non-nullable type Int! not to be null at value.foo.',
            ['foo'],
        ];
        yield [
            123,
            'Expected type TestInputObject to be an object.',
            null,
        ];
        yield [
            new class() {
            },
            'Expected type TestInputObject to be an object.',
            null,
        ];
    }
}
