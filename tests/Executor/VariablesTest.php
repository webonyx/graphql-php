<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SyntaxError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Tests\Executor\TestClasses\ComplexScalar;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

/**
 * @see describe('Execute: Handles inputs', () => {
 * @see describe('Handles objects and nullability
 *
 * @phpstan-import-type UnnamedFieldDefinitionConfig from \GraphQL\Type\Definition\FieldDefinition
 * @phpstan-import-type UnnamedArgumentConfig from \GraphQL\Type\Definition\Argument
 */
final class VariablesTest extends TestCase
{
    use ArraySubsetAsserts;

    public function testUsingInlineStructs(): void
    {
        // executes with complex input:
        $result = $this->executeQuery('
        {
          fieldWithObjectInput(input: {a: "foo", b: ["bar"], c: "baz"})
        }
        ');

        $expected = [
            'data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}'],
        ];
        self::assertSame($expected, $result->toArray());

        // properly parses single value to list:
        $result = $this->executeQuery('
        {
          fieldWithObjectInput(input: {a: "foo", b: "bar", c: "baz"})
        }
        ');
        $expected = ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']];

        self::assertSame($expected, $result->toArray());

        $result = $this->executeQuery(
            '
            query ($input: TestInputObject) {
              fieldWithObjectInput(input: $input)
            }
            ',
            ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => 'baz']]
        );
        $expected = ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']];

        self::assertSame($expected, $result->toArray());

        // properly parses null value to null
        $result = $this->executeQuery('
        {
          fieldWithObjectInput(input: {a: null, b: null, c: "C", d: null})
        }
        ');
        $expected = ['data' => ['fieldWithObjectInput' => '{"a":null,"b":null,"c":"C","d":null}']];

        self::assertSame($expected, $result->toArray());

        // properly parses null value in list
        $result = $this->executeQuery('
        {
          fieldWithObjectInput(input: {b: ["A",null,"C"], c: "C"})
        }
        ');
        $expected = ['data' => ['fieldWithObjectInput' => '{"b":["A",null,"C"],"c":"C"}']];

        self::assertSame($expected, $result->toArray());

        // does not use incorrect value
        $result = $this->executeQuery('
        {
          fieldWithObjectInput(input: ["foo", "bar", "baz"])
        }
        ');

        $expected = [
            'data' => ['fieldWithObjectInput' => null],
            'errors' => [
                [
                    'message' => 'Argument "input" has invalid value ["foo", "bar", "baz"].',
                    'path' => ['fieldWithObjectInput'],
                    'locations' => [['line' => 3, 'column' => 39]],
                ],
            ],
        ];
        self::assertArraySubset($expected, $result->toArray());

        // properly runs parseLiteral on complex scalar types
        $result = $this->executeQuery('
        {
          fieldWithObjectInput(input: {c: "foo", d: "SerializedValue"})
        }
        ');
        self::assertSame(
            ['data' => ['fieldWithObjectInput' => '{"c":"foo","d":"DeserializedValue"}']],
            $result->toArray()
        );
    }

    /**
     * @param array<string, mixed>|null $variableValues
     *
     * @throws \JsonException
     * @throws InvariantViolation
     * @throws SyntaxError
     */
    private function executeQuery(string $query, ?array $variableValues = null): ExecutionResult
    {
        return Executor::execute(
            $this->schema(),
            Parser::parse($query),
            null,
            null,
            $variableValues
        );
    }

    /** @throws InvariantViolation */
    public function schema(): Schema
    {
        $ComplexScalarType = new ComplexScalar();

        $TestInputObject = new InputObjectType([
            'name' => 'TestInputObject',
            'fields' => [
                'a' => ['type' => Type::string()],
                'b' => ['type' => Type::listOf(Type::string())],
                'c' => ['type' => Type::nonNull(Type::string())],
                'd' => ['type' => $ComplexScalarType],
            ],
        ]);

        $TestNestedInputObject = new InputObjectType([
            'name' => 'TestNestedInputObject',
            'fields' => [
                'na' => ['type' => Type::nonNull($TestInputObject)],
                'nb' => ['type' => Type::nonNull(Type::string())],
            ],
        ]);

        $TestEnum = new EnumType([
            'name' => 'TestEnum',
            'values' => [
                'NULL' => ['value' => null],
                'NAN' => ['value' => acos(8)],
                'FALSE' => ['value' => false],
                'CUSTOM' => ['value' => 'custom value'],
                'DEFAULT_VALUE' => [],
            ],
        ]);

        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'fieldWithEnumInput' => $this->fieldWithInputArg(['type' => $TestEnum]),
                'fieldWithNonNullableEnumInput' => $this->fieldWithInputArg(['type' => Type::nonNull($TestEnum)]),
                'fieldWithObjectInput' => $this->fieldWithInputArg(['type' => $TestInputObject]),
                'fieldWithNullableStringInput' => $this->fieldWithInputArg(['type' => Type::string()]),
                'fieldWithNonNullableStringInput' => $this->fieldWithInputArg(['type' => Type::nonNull(Type::string())]),
                'fieldWithDefaultArgumentValue' => $this->fieldWithInputArg([
                    'type' => Type::string(),
                    'defaultValue' => 'Hello World',
                ]),
                'fieldWithNonNullableStringInputAndDefaultArgumentValue' => $this->fieldWithInputArg([
                    'type' => Type::nonNull(Type::string()),
                    'defaultValue' => 'Hello World',
                ]),
                'fieldWithNestedInputObject' => $this->fieldWithInputArg([
                    'type' => $TestNestedInputObject,
                    'defaultValue' => 'Hello World',
                ]),
                'list' => $this->fieldWithInputArg(['type' => Type::listOf(Type::string())]),
                'nnList' => $this->fieldWithInputArg(['type' => Type::nonNull(Type::listOf(Type::string()))]),
                'listNN' => $this->fieldWithInputArg(['type' => Type::listOf(Type::nonNull(Type::string()))]),
                'nnListNN' => $this->fieldWithInputArg(['type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string())))]),
            ],
        ]);

        return new Schema(['query' => $TestType]);
    }

    /**
     * @param UnnamedArgumentConfig $inputArg
     *
     * @throws InvariantViolation
     *
     * @return UnnamedFieldDefinitionConfig
     */
    private function fieldWithInputArg(array $inputArg): array
    {
        return [
            'type' => Type::string(),
            'args' => ['input' => $inputArg],
            'resolve' => static function ($_, array $args): ?string {
                if (isset($args['input'])) {
                    return Utils::printSafeJson($args['input']);
                }

                if (array_key_exists('input', $args) && $args['input'] === null) {
                    return 'null';
                }

                return null;
            },
        ];
    }

    public function testUsingVariables(): void
    {
        $doc = '
            query q($input:TestInputObject) {
              fieldWithObjectInput(input: $input)
            }
        ';

        // executes with complex input:
        $params = ['input' => ['a' => 'foo', 'b' => ['bar'], 'c' => 'baz']];
        $result = $this->executeQuery($doc, $params);

        self::assertSame(
            ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']],
            $result->toArray()
        );

        // uses undefined when variable not provided
        $result = $this->executeQuery(
            '
          query q($input: String) {
            fieldWithNullableStringInput(input: $input)
          }',
            [] // Intentionally missing variable values.
        );
        $expected = [
            'data' => ['fieldWithNullableStringInput' => null],
        ];
        self::assertEquals($expected, $result->toArray());

        // uses null when variable provided explicit null value
        $result = $this->executeQuery(
            '
          query q($input: String) {
            fieldWithNullableStringInput(input: $input)
          }',
            ['input' => null]
        );

        $expected = [
            'data' => ['fieldWithNullableStringInput' => 'null'],
        ];
        self::assertSame($expected, $result->toArray());

        // uses default value when not provided:
        $result = $this->executeQuery('
          query ($input: TestInputObject = {a: "foo", b: ["bar"], c: "baz"}) {
            fieldWithObjectInput(input: $input)
          }
        ');

        $expected = [
            'data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}'],
        ];
        self::assertSame($expected, $result->toArray());

        // does not use default value when provided
        $result = $this->executeQuery(
            'query q($input: String = "Default value") {
            fieldWithNullableStringInput(input: $input)
          }',
            ['input' => 'Variable value']
        );

        $expected = [
            'data' => ['fieldWithNullableStringInput' => '"Variable value"'],
        ];
        self::assertSame($expected, $result->toArray());

        // uses explicit null value instead of default value
        $result = $this->executeQuery(
            '
          query q($input: String = "Default value") {
            fieldWithNullableStringInput(input: $input)
          }',
            ['input' => null]
        );

        $expected = [
            'data' => ['fieldWithNullableStringInput' => 'null'],
        ];
        self::assertSame($expected, $result->toArray());

        // uses null default value when not provided
        $result = $this->executeQuery(
            '
          query q($input: String = null) {
            fieldWithNullableStringInput(input: $input)
          }',
            [] // Intentionally missing variable values.
        );

        $expected = [
            'data' => ['fieldWithNullableStringInput' => 'null'],
        ];
        self::assertSame($expected, $result->toArray());

        // properly parses single value to list:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => 'baz']];
        $result = $this->executeQuery($doc, $params);
        self::assertSame(
            ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']],
            $result->toArray()
        );

        // executes with complex scalar input:
        $params = ['input' => ['c' => 'foo', 'd' => 'SerializedValue']];
        $result = $this->executeQuery($doc, $params);
        $expected = [
            'data' => ['fieldWithObjectInput' => '{"c":"foo","d":"DeserializedValue"}'],
        ];
        self::assertSame($expected, $result->toArray());

        // errors on null for nested non-null:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => null]];
        $result = $this->executeQuery($doc, $params);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" got invalid value null at "input.c"; Expected non-nullable type "String!" not to be null.',
                    'locations' => [['line' => 2, 'column' => 21]],
                ],
            ],
        ];

        self::assertSame($expected, $result->toArray());

        // errors on incorrect type:
        $params = ['input' => 'foo bar'];
        $result = $this->executeQuery($doc, $params);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" got invalid value "foo bar"; Expected type "TestInputObject" to be an object.',
                    'locations' => [['line' => 2, 'column' => 21]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());

        // errors on omission of nested non-null:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar']];

        $result = $this->executeQuery($doc, $params);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" got invalid value {"a":"foo","b":"bar"}; Field "c" of required type "String!" was not provided.',
                    'locations' => [['line' => 2, 'column' => 21]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());

        // errors on deep nested errors and with many errors
        $nestedDoc = '
          query q($input: TestNestedInputObject) {
            fieldWithNestedObjectInput(input: $input)
          }
        ';
        $params = ['input' => ['na' => ['a' => 'foo']]];

        $result = $this->executeQuery($nestedDoc, $params);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" got invalid value {"a":"foo"} at "input.na"; Field "c" of required type "String!" was not provided.',
                    'locations' => [['line' => 2, 'column' => 19]],
                ],
                [
                    'message' => 'Variable "$input" got invalid value {"na":{"a":"foo"}}; Field "nb" of required type "String!" was not provided.',
                    'locations' => [['line' => 2, 'column' => 19]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());

        // errors on addition of unknown input field
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => 'baz', 'extra' => 'dog']];
        $result = $this->executeQuery($doc, $params);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" got invalid value '
                        . '{"a":"foo","b":"bar","c":"baz","extra":"dog"}; '
                        . 'Field "extra" is not defined by type "TestInputObject".',
                    'locations' => [['line' => 2, 'column' => 21]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    public function testUsingStdClassVariables(): void
    {
        $doc = '
            query q($input:TestNestedInputObject) {
                fieldWithNestedInputObject(input: $input)
            }
        ';

        // executes with complex input:
        $params = ['input' => (object) ['na' => (object) ['a' => 'foo', 'b' => ['bar'], 'c' => 'baz'], 'nb' => 'test']];
        $result = $this->executeQuery($doc, $params);

        self::assertSame(
            ['data' => ['fieldWithNestedInputObject' => '{"na":{"a":"foo","b":["bar"],"c":"baz"},"nb":"test"}']],
            $result->toArray()
        );
    }

    /**
     * Describe: Handles custom enum values.
     */

    /** @see it('allows custom enum values as inputs') */
    public function testAllowsCustomEnumValuesAsInputs(): void
    {
        $result = $this->executeQuery('
        {
          null: fieldWithEnumInput(input: NULL)
          NaN: fieldWithEnumInput(input: NAN)
          false: fieldWithEnumInput(input: FALSE)
          customValue: fieldWithEnumInput(input: CUSTOM)
          defaultValue: fieldWithEnumInput(input: DEFAULT_VALUE)
        }
        ');

        $expected = [
            'data' => [
                'null' => 'null',
                'NaN' => 'NAN',
                'false' => 'false',
                'customValue' => '"custom value"',
                'defaultValue' => '"DEFAULT_VALUE"',
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows non-nullable inputs to have null as enum custom value') */
    public function testAllowsNonNullableInputsToHaveNullAsEnumCustomValue(): void
    {
        $result = $this->executeQuery('
        {
          fieldWithNonNullableEnumInput(input: NULL)
        }
        ');

        self::assertSame(
            ['data' => ['fieldWithNonNullableEnumInput' => 'null']],
            $result->toArray()
        );
    }

    /**
     * Describe: Handles nullable scalars.
     */

    /** @see it('allows nullable inputs to be omitted') */
    public function testAllowsNullableInputsToBeOmitted(): void
    {
        $result = $this->executeQuery('
      {
        fieldWithNullableStringInput
      }
        ');
        $expected = [
            'data' => ['fieldWithNullableStringInput' => null],
        ];

        self::assertEquals($expected, $result->toArray());
    }

    /** @see it('allows nullable inputs to be omitted in a variable') */
    public function testAllowsNullableInputsToBeOmittedInAVariable(): void
    {
        $result = $this->executeQuery('
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ');
        $expected = ['data' => ['fieldWithNullableStringInput' => null]];

        self::assertEquals($expected, $result->toArray());
    }

    /** @see it('allows nullable inputs to be omitted in an unlisted variable') */
    public function testAllowsNullableInputsToBeOmittedInAnUnlistedVariable(): void
    {
        $result = $this->executeQuery('
      query SetsNullable {
        fieldWithNullableStringInput(input: $value)
      }
      ');
        $expected = ['data' => ['fieldWithNullableStringInput' => null]];
        self::assertEquals($expected, $result->toArray());
    }

    // Describe: Handles non-nullable scalars

    /** @see it('allows nullable inputs to be set to null in a variable') */
    public function testAllowsNullableInputsToBeSetToNullInAVariable(): void
    {
        $result = $this->executeQuery('
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ');
        $expected = ['data' => ['fieldWithNullableStringInput' => null]];

        self::assertEquals($expected, $result->toArray());
    }

    /** @see it('allows nullable inputs to be set to a value in a variable') */
    public function testAllowsNullableInputsToBeSetToAValueInAVariable(): void
    {
        $doc = '
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ';
        $result = $this->executeQuery($doc, ['value' => 'a']);
        $expected = ['data' => ['fieldWithNullableStringInput' => '"a"']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows nullable inputs to be set to a value directly') */
    public function testAllowsNullableInputsToBeSetToAValueDirectly(): void
    {
        $result = $this->executeQuery('
      {
        fieldWithNullableStringInput(input: "a")
      }
        ');
        $expected = ['data' => ['fieldWithNullableStringInput' => '"a"']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows non-nullable inputs to be omitted given a default') */
    public function testAllowsNonNullableInputsToBeOmittedGivenADefault(): void
    {
        $result = $this->executeQuery('
        query SetsNonNullable($value: String = "default") {
          fieldWithNonNullableStringInput(input: $value)
        }
        ');
        $expected = [
            'data' => ['fieldWithNonNullableStringInput' => '"default"'],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('does not allow non-nullable inputs to be omitted in a variable') */
    public function testDoesntAllowNonNullableInputsToBeOmittedInAVariable(): void
    {
        $result = $this->executeQuery('
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ');

        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$value" of required type "String!" was not provided.',
                    'locations' => [['line' => 2, 'column' => 31]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('does not allow non-nullable inputs to be set to null in a variable') */
    public function testDoesNotAllowNonNullableInputsToBeSetToNullInAVariable(): void
    {
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $result = $this->executeQuery($doc, ['value' => null]);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$value" of non-null type "String!" must not be null.',
                    'locations' => [['line' => 2, 'column' => 31]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows non-nullable inputs to be set to a value in a variable') */
    public function testAllowsNonNullableInputsToBeSetToAValueInAVariable(): void
    {
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $result = $this->executeQuery($doc, ['value' => 'a']);
        $expected = ['data' => ['fieldWithNonNullableStringInput' => '"a"']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows non-nullable inputs to be set to a value directly') */
    public function testAllowsNonNullableInputsToBeSetToAValueDirectly(): void
    {
        $result = $this->executeQuery('
      {
        fieldWithNonNullableStringInput(input: "a")
      }
        ');
        $expected = ['data' => ['fieldWithNonNullableStringInput' => '"a"']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('reports error for missing non-nullable inputs') */
    public function testReportsErrorForMissingNonNullableInputs(): void
    {
        $result = $this->executeQuery('
      {
        fieldWithNonNullableStringInput
      }
        ');
        $expected = [
            'data' => ['fieldWithNonNullableStringInput' => null],
            'errors' => [
                [
                    'message' => 'Argument "input" of required type "String!" was not provided.',
                    'locations' => [['line' => 3, 'column' => 9]],
                    'path' => ['fieldWithNonNullableStringInput'],
                ],
            ],
        ];
        self::assertEquals($expected, $result->toArray());
    }

    // Describe: Handles lists and nullability

    /** @see it('reports error for array passed into string input') */
    public function testReportsErrorForArrayPassedIntoStringInput(): void
    {
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $variables = ['value' => [1, 2, 3]];
        $result = $this->executeQuery($doc, $variables);

        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$value" got invalid value [1,2,3]; String cannot represent a non string value: [1,2,3]',
                    'locations' => [
                        ['line' => 2, 'column' => 31],
                    ],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('reports error for non-provided variables for non-nullable inputs') */
    public function testReportsErrorForNonProvidedVariablesForNonNullableInputs(): void
    {
        // Note: this test would typically fail validation before encountering
        // this execution error, however for queries which previously validated
        // and are being run against a new schema which have introduced a breaking
        // change to make a formerly non-required argument required, this asserts
        // failure before allowing the underlying code to receive a non-null value.
        $result = $this->executeQuery('
      {
        fieldWithNonNullableStringInput(input: $foo)
      }
        ');
        $expected = [
            'data' => ['fieldWithNonNullableStringInput' => null],
            'errors' => [
                [
                    'message' => 'Argument "input" of required type "String!" was provided the '
                        . 'variable "$foo" which was not provided a runtime value.',
                    'locations' => [['line' => 3, 'column' => 48]],
                    'path' => ['fieldWithNonNullableStringInput'],
                ],
            ],
        ];
        self::assertEquals($expected, $result->toArray());
    }

    /** @see it('allows lists to be null') */
    public function testAllowsListsToBeNull(): void
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => null]);
        $expected = ['data' => ['list' => 'null']];

        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows lists to contain values') */
    public function testAllowsListsToContainValues(): void
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => ['A']]);
        $expected = ['data' => ['list' => '["A"]']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows lists to contain null') */
    public function testAllowsListsToContainNull(): void
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => ['A', null, 'B']]);
        $expected = ['data' => ['list' => '["A",null,"B"]']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('does not allow non-null lists to be null') */
    public function testDoesNotAllowNonNullListsToBeNull(): void
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => null]);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" of non-null type "[String]!" must not be null.',
                    'locations' => [['line' => 2, 'column' => 17]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows non-null lists to contain values') */
    public function testAllowsNonNullListsToContainValues(): void
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => ['A']]);
        $expected = ['data' => ['nnList' => '["A"]']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows non-null lists to contain null') */
    public function testAllowsNonNullListsToContainNull(): void
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => ['A', null, 'B']]);
        $expected = ['data' => ['nnList' => '["A",null,"B"]']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows lists of non-nulls to be null') */
    public function testAllowsListsOfNonNullsToBeNull(): void
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => null]);
        $expected = ['data' => ['listNN' => 'null']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows lists of non-nulls to contain values') */
    public function testAllowsListsOfNonNullsToContainValues(): void
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => ['A']]);
        $expected = ['data' => ['listNN' => '["A"]']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('does not allow lists of non-nulls to contain null') */
    public function testDoesNotAllowListsOfNonNullsToContainNull(): void
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => ['A', null, 'B']]);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" got invalid value null at "input[1]"; Expected non-nullable type "String!" not to be null.',
                    'locations' => [['line' => 2, 'column' => 17]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('does not allow non-null lists of non-nulls to be null') */
    public function testDoesNotAllowNonNullListsOfNonNullsToBeNull(): void
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => null]);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" of non-null type "[String!]!" must not be null.',
                    'locations' => [['line' => 2, 'column' => 17]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('allows non-null lists of non-nulls to contain values') */
    public function testAllowsNonNullListsOfNonNullsToContainValues(): void
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => ['A']]);
        $expected = ['data' => ['nnListNN' => '["A"]']];
        self::assertSame($expected, $result->toArray());
    }

    // Describe: Execute: Uses argument default values

    /** @see it('does not allow non-null lists of non-nulls to contain null') */
    public function testDoesNotAllowNonNullListsOfNonNullsToContainNull(): void
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $result = $this->executeQuery($doc, ['input' => ['A', null, 'B']]);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" got invalid value null at "input[1]"; Expected non-nullable type "String!" not to be null.',
                    'locations' => [['line' => 2, 'column' => 17]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('does not allow invalid types to be used as values') */
    public function testDoesNotAllowInvalidTypesToBeUsedAsValues(): void
    {
        $doc = '
        query q($input: TestType!) {
          fieldWithObjectInput(input: $input)
        }
        ';
        $vars = ['input' => ['list' => ['A', 'B']]];
        $result = $this->executeQuery($doc, $vars);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" expected value of type "TestType!" which cannot be used as an input type.',
                    'locations' => [['line' => 2, 'column' => 25]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('does not allow unknown types to be used as values') */
    public function testDoesNotAllowUnknownTypesToBeUsedAsValues(): void
    {
        $doc = '
        query q($input: UnknownType!) {
          fieldWithObjectInput(input: $input)
        }
        ';
        $vars = ['input' => 'whoknows'];

        $result = $this->executeQuery($doc, $vars);
        $expected = [
            'errors' => [
                [
                    'message' => 'Variable "$input" expected value of type "UnknownType!" which cannot be used as an input type.',
                    'locations' => [['line' => 2, 'column' => 25]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('when no argument provided') */
    public function testWhenNoArgumentProvided(): void
    {
        $result = $this->executeQuery('{
        fieldWithDefaultArgumentValue
        }');

        self::assertSame(
            ['data' => ['fieldWithDefaultArgumentValue' => '"Hello World"']],
            $result->toArray()
        );
    }

    /** @see it('when omitted variable provided') */
    public function testWhenOmittedVariableProvided(): void
    {
        $result = $this->executeQuery('query optionalVariable($optional: String) {
            fieldWithDefaultArgumentValue(input: $optional)
        }');

        self::assertSame(
            ['data' => ['fieldWithDefaultArgumentValue' => '"Hello World"']],
            $result->toArray()
        );
    }

    /** @see it('not when argument cannot be coerced') */
    public function testNotWhenArgumentCannotBeCoerced(): void
    {
        $result = $this->executeQuery('{
            fieldWithDefaultArgumentValue(input: WRONG_TYPE)
        }');

        $expected = [
            'data' => ['fieldWithDefaultArgumentValue' => null],
            'errors' => [
                [
                    'message' => 'Argument "input" has invalid value WRONG_TYPE.',
                    'locations' => [['line' => 2, 'column' => 50]],
                    'path' => ['fieldWithDefaultArgumentValue'],
                ],
            ],
        ];

        self::assertEquals($expected, $result->toArray());
    }

    /** @see it('when no runtime value is provided to a non-null argument') */
    public function testWhenNoRuntimeValueIsProvidedToANonNullArgument(): void
    {
        $result = $this->executeQuery('
        query optionalVariable($optional: String) {
          fieldWithNonNullableStringInputAndDefaultArgumentValue(input: $optional)
        }
      ');

        $expected = [
            'data' => ['fieldWithNonNullableStringInputAndDefaultArgumentValue' => '"Hello World"'],
        ];
        self::assertSame($expected, $result->toArray());
    }
}
