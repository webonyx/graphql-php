<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\ClientAware;
use GraphQL\Error\CoercionError;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\ProvidesExtensions;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Value;
use PHPUnit\Framework\TestCase;

/**
 * @see coerceInputValue-test.ts
 *
 * @phpstan-import-type InputPath from CoercionError
 */
final class CoerceInputValueTest extends TestCase
{
    private NonNull $testNonNull;

    private CustomScalarType $testScalar;

    private EnumType $testEnum;

    private InputObjectType $testInputObject;

    /** @var ListOfType<ScalarType> */
    private ListOfType $testList;

    /** @var ListOfType<ListOfType<ScalarType>> */
    private ListOfType $testNestedList;

    protected function setUp(): void
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

        $this->testList = new ListOfType(Type::int());

        $this->testNestedList = new ListOfType($this->testList);
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

    /** @see it('it('returns a thrown error', () => {', () => { */
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

    /** @see it('returns an error for misspelled enum value', () => { */
    public function testReturnsAnErrorForMisspelledEnumValue(): void
    {
        $result = Value::coerceInputValue('foo', $this->testEnum);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Value "foo" does not exist in "TestEnum" enum. Did you mean the enum value "FOO"?',
            null,
            'foo',
        )]);
    }

    /** @see it('returns an error for incorrect value type', () => { */
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

    public function testPropagatesClientSafeError(): void
    {
        $message = 'message';
        $value = ['value' => 1];

        $clientSafeException = new class($message) extends \Exception implements ClientAware {
            public function isClientSafe(): bool
            {
                return true;
            }
        };

        $scalar = new CustomScalarType([
            'name' => 'TestScalar',
            'parseValue' => function () use ($clientSafeException) {
                throw $clientSafeException;
            },
            'parseLiteral' => fn () => null,
        ]);

        $result = Value::coerceInputValue($value, $scalar);
        $this->expectGraphQLError($result, [CoercionError::make(
            $message,
            null,
            $value,
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

    /** @return iterable<array{mixed}> */
    public static function validInputObjects(): iterable
    {
        yield [['foo' => 123]];
        yield [(object) ['foo' => 123]];
    }

    /**
     * @phpstan-param array{code: string}|null $extensions
     *
     * @dataProvider provideInputObjectParseValueErrors
     */
    public function testInputObjectParseValueErrors(
        \Throwable $exception,
        string $message,
        bool $isClientSafe,
        ?array $extensions
    ): void {
        $parsedValue = null;
        $input = new InputObjectType([
            'name' => 'TestInput',
            'fields' => [
                'id' => Type::id(),
                'limit' => [
                    'type' => Type::int(),
                    'defaultValue' => 7,
                ],
            ],
            'parseValue' => static function (array $value) use ($exception, &$parsedValue): void {
                $parsedValue = $value;
                throw $exception;
            },
        ]);
        $originalValue = ['id' => 123];
        $path = ['input', 2];

        $result = Value::coerceInputValue($originalValue, $input, $path);

        self::assertSame(['id' => '123', 'limit' => 7], $parsedValue);
        self::assertNull($result['value']);
        self::assertNotNull($result['errors']);
        self::assertCount(1, $result['errors']);
        $error = $result['errors'][0];
        self::assertSame($message, $error->getMessage());
        self::assertSame($isClientSafe, $error->isClientSafe());
        self::assertSame($extensions, $error->getExtensions());
        self::assertSame($exception, $error->getPrevious());
        self::assertSame($originalValue, $error->invalidValue);
        self::assertSame($path, $error->inputPath);
    }

    /** @phpstan-return \Generator<string, array{\Throwable, string, bool, array{code: string}|null}> */
    public static function provideInputObjectParseValueErrors(): \Generator
    {
        foreach ([true, false] as $isClientSafe) {
            $exception = new class($isClientSafe) extends \Exception implements ClientAware, ProvidesExtensions {
                private bool $isClientSafe;

                public function __construct(bool $isClientSafe)
                {
                    parent::__construct('Client-aware failure.');
                    $this->isClientSafe = $isClientSafe;
                }

                public function isClientSafe(): bool
                {
                    return $this->isClientSafe;
                }

                /** @phpstan-return array{code: string} */
                public function getExtensions(): array
                {
                    return ['code' => 'INPUT_INVALID'];
                }
            };

            $case = $isClientSafe
                ? 'client safe with extensions'
                : 'client unsafe with extensions';
            $message = $isClientSafe
                ? 'Client-aware failure.'
                : 'Expected type "TestInput".';

            yield $case => [
                $exception,
                $message,
                $isClientSafe,
                ['code' => 'INPUT_INVALID'],
            ];
        }

        yield 'unsafe exception' => [new \RuntimeException('Private detail.'), 'Expected type "TestInput".', false, null];
        yield 'type error' => [new \TypeError('Private type detail.'), 'Expected type "TestInput".', false, null];
        yield 'graphql error' => [
            new Error('GraphQL failure.', null, null, [], null, null, ['code' => 'GRAPHQL_INVALID']),
            'GraphQL failure.',
            true,
            ['code' => 'GRAPHQL_INVALID'],
        ];
        yield 'graphql error with unsafe previous' => [
            new Error(
                'GraphQL failure.',
                null,
                null,
                [],
                null,
                new \RuntimeException('Private detail.'),
                ['code' => 'GRAPHQL_INVALID'],
            ),
            'GraphQL failure.',
            false,
            ['code' => 'GRAPHQL_INVALID'],
        ];
    }

    public function testCollectsNestedInputObjectParseValueErrorsWithoutParsingParent(): void
    {
        $parsedValues = [];
        $child = new InputObjectType([
            'name' => 'TestInput',
            'fields' => ['value' => Type::int()],
            'parseValue' => static function (array $value) use (&$parsedValues): array {
                $parsedValues[] = $value['value'];
                if ($value['value'] !== 4) {
                    throw new Error("Rejected {$value['value']}.");
                }

                return $value;
            },
        ]);
        $parentCalls = 0;
        $parent = new InputObjectType([
            'name' => 'ParentInput',
            'fields' => ['items' => Type::listOf(Type::listOf($child))],
            'parseValue' => static function (array $value) use (&$parentCalls): array {
                ++$parentCalls;

                return $value;
            },
        ]);

        $result = Value::coerceInputValue([
            'items' => [[['value' => 1], ['value' => 'invalid']], [['value' => 3], ['value' => 4]]],
        ], $parent, ['root']);

        $this->expectGraphQLError($result, [
            CoercionError::make('Rejected 1.', ['root', 'items', 0, 0], ['value' => 1]),
            CoercionError::make(
                'Int cannot represent non-integer value: "invalid"',
                ['root', 'items', 0, 1, 'value'],
                'invalid',
            ),
            CoercionError::make('Rejected 3.', ['root', 'items', 1, 0], ['value' => 3]),
        ]);
        self::assertSame([1, 3, 4], $parsedValues);
        self::assertSame(0, $parentCalls);
    }

    /** @see it('returns an error for a non-object type', () => { */
    public function testReturnsAnErrorForANonObjectType(): void
    {
        $result = Value::coerceInputValue(123, $this->testInputObject);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Expected type "TestInputObject" to be an object.',
            null,
            123
        )]);
    }

    /** @see it('returns an error for an invalid field', () => { */
    public function testReturnsAnErrorForAnInvalidField(): void
    {
        $notInt = new \stdClass();
        $result = Value::coerceInputValue(['foo' => $notInt], $this->testInputObject);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Int cannot represent non-integer value: {}',
            ['foo'],
            $notInt
        )]);
    }

    /** @see it('returns multiple errors for multiple invalid fields', () => { */
    public function testReturnsMultipleErrorsForMultipleInvalidFields(): void
    {
        $result = Value::coerceInputValue(['foo' => 'abc', 'bar' => 'def'], $this->testInputObject);
        $this->expectGraphQLError($result, [
            CoercionError::make(
                'Int cannot represent non-integer value: "abc"',
                ['foo'],
                'abc'
            ),
            CoercionError::make(
                'Int cannot represent non-integer value: "def"',
                ['bar'],
                'def'
            ),
        ]);
    }

    /** @see it('returns error for a missing required field', () => { */
    public function testReturnsErrorForAMissingRequiredField(): void
    {
        $result = Value::coerceInputValue(['bar' => 123], $this->testInputObject);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Field "foo" of required type "Int!" was not provided.',
            null,
            ['bar' => 123]
        )]);
    }

    /** @see it('returns error for an unknown field', () => { */
    public function testReturnsErrorForAnUnknownField(): void
    {
        $result = Value::coerceInputValue(['foo' => 123, 'unknownField' => 123], $this->testInputObject);
        $this->expectGraphQLError($result, [CoercionError::make(
            'Field "unknownField" is not defined by type "TestInputObject".',
            null,
            ['foo' => 123, 'unknownField' => 123],
        )]);
    }

    /** @see it('returns error for a misspelled field', () => { */
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
     * @param mixed $defaultValue Anything goes
     *
     * @throws InvariantViolation
     */
    private function makeTestInputObject($defaultValue): InputObjectType
    {
        return new InputObjectType([
            'name' => 'TestInputObject',
            'fields' => [
                'foo' => [
                    'type' => new CustomScalarType([
                        'name' => 'TestScalar',
                        'parseValue' => static fn ($value) => $value,
                        'parseLiteral' => static fn () => null,
                    ]),
                    'defaultValue' => $defaultValue,
                ],
            ],
        ]);
    }

    /**
     * @see describe('for GraphQLInputObject with default value', () => {
     * @see it('returns no errors for valid input value', () => {
     */
    public function testReturnsNoErrorsForValidInputValue(): void
    {
        $result = Value::coerceInputValue(['foo' => 5], $this->makeTestInputObject(7));
        $this->expectGraphQLValue($result, ['foo' => 5]);
    }

    /** @see it('returns object with default value', () => { */
    public function testReturnsObjectWithDefaultValue(): void
    {
        $result = Value::coerceInputValue([], $this->makeTestInputObject(7));
        $this->expectGraphQLValue($result, ['foo' => 7]);
    }

    /**
     * @see it('returns null as value', () => {
     * @see it('returns NaN as value', () => {
     */
    public function testReturnsNullAsValue(): void
    {
        $result = Value::coerceInputValue([], $this->makeTestInputObject(null));
        $this->expectGraphQLValue($result, ['foo' => null]);
    }

    /**
     * @see describe('for GraphQLList', () => {
     * @see it('returns no error for a valid input', () => {
     */
    public function testGraphQLListReturnsNoErrorForAValidInput(): void
    {
        $result = Value::coerceInputValue([1, 2, 3], $this->testList);
        $this->expectGraphQLValue($result, [1, 2, 3]);
    }

    /** @see it('returns no error for a valid iterable input', () => { */
    public function testReturnsNoErrorForAValidIterableInput(): void
    {
        $listGenerator = function (): iterable {
            yield 1;
            yield 2;
            yield 3;
        };

        $result = Value::coerceInputValue($listGenerator(), $this->testList);
        $this->expectGraphQLValue($result, [1, 2, 3]);
    }

    /** @see it('returns an error for an invalid input', () => { */
    public function testReturnsAnErrorForAnInvalidInput(): void
    {
        $result = Value::coerceInputValue([1, 'b', true, 4], $this->testList);
        $this->expectGraphQLError($result, [
            CoercionError::make(
                'Int cannot represent non-integer value: "b"',
                [1],
                'b',
            ),
            CoercionError::make(
                'Int cannot represent non-integer value: true',
                [2],
                true,
            ),
        ]);
    }

    /** @see it('returns a list for a non-list value', () => { */
    public function testReturnsAListForANonListValue(): void
    {
        $result = Value::coerceInputValue(42, $this->testList);
        $this->expectGraphQLValue($result, [42]);
    }

    /** @see it('returns a list for a non-list object value', () => { */
    public function testReturnsAListForANonListObjectValue(): void
    {
        $TestListOfObjects = new ListOfType(
            new InputObjectType([
                'name' => 'TestObject',
                'fields' => [
                    'length' => [
                        'type' => Type::int(),
                    ],
                ],
            ])
        );

        $result = Value::coerceInputValue((object) ['length' => 100500], $TestListOfObjects);
        $this->expectGraphQLValue($result, [['length' => 100500]]);
    }

    /** @see it('returns an error for a non-list invalid value', () => { */
    public function testReturnsAnErrorForANonListInvalidValue(): void
    {
        $result = Value::coerceInputValue('INVALID', $this->testList);
        $this->expectGraphQLError($result, [
            CoercionError::make(
                'Int cannot represent non-integer value: "INVALID"',
                null,
                'INVALID',
            ),
        ]);
    }

    /** @see it('returns null for a null value', () => { */
    public function testReturnsNullForANullValue(): void
    {
        $result = Value::coerceInputValue(null, $this->testList);
        $this->expectGraphQLValue($result, null);
    }

    /**
     * @see describe('for nested GraphQLList', () => {
     * @see it('returns no error for a valid input', () => {
     */
    public function testNestedGraphQLListReturnsNoErrorForAValidInput(): void
    {
        $result = Value::coerceInputValue([[1], [2, 3]], $this->testNestedList);
        $this->expectGraphQLValue($result, [[1], [2, 3]]);
    }

    /** @see it('returns a list for a non-list value', () => { */
    public function testNestedGraphQLListReturnsAListForANonListValue(): void
    {
        $result = Value::coerceInputValue(42, $this->testNestedList);
        $this->expectGraphQLValue($result, [[42]]);
    }

    /** @see it('returns null for a null value', () => { */
    public function testNestedGraphQLListReturnsNullForANullValue(): void
    {
        $result = Value::coerceInputValue(null, $this->testNestedList);
        $this->expectGraphQLValue($result, null);
    }

    /** @see it('returns nested lists for nested non-list values', () => { */
    public function testReturnsNestedListsForNestedNonListValue(): void
    {
        $result = Value::coerceInputValue([1, 2, 3], $this->testNestedList);
        $this->expectGraphQLValue($result, [[1], [2], [3]]);
    }

    /** @see it('returns nested null for nested null values', () => { */
    public function testReturnsNestedNullForNestedNullValues(): void
    {
        $result = Value::coerceInputValue([42, [null], null], $this->testNestedList);
        $this->expectGraphQLValue($result, [[42], [null], null]);
    }

    /*
     * @see describe('with default onError', () => {
     * @see it('throw error without path', () => {
     * @see it('throw error with path', () => {
     *
     * Unnecessary because we do not implement the callback variant coerceInputValue.
     */

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
     * @param mixed $result
     * @param mixed $expected
     */
    private function expectGraphQLValue($result, $expected): void
    {
        self::assertIsArray($result);
        self::assertNull($result['errors']);
        self::assertSame($expected, $result['value']);
    }
}
