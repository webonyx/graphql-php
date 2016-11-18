<?php
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Error\Error;
use GraphQL\Executor\Executor;
use GraphQL\Error\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class VariablesTest extends \PHPUnit_Framework_TestCase
{
    // Execute: Handles inputs
    // Handles objects and nullability

    /**
     * @describe using inline structs
     */
    public function testUsingInlineStructs()
    {
        // executes with complex input:
        $doc = '
        {
          fieldWithObjectInput(input: {a: "foo", b: ["bar"], c: "baz"})
        }
        ';
        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
            'fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}'
          ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());

        // properly parses single value to list:
        $doc = '
        {
          fieldWithObjectInput(input: {a: "foo", b: "bar", c: "baz"})
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());

        // properly parses null value to null
        $doc = '
        {
          fieldWithObjectInput(input: {a: null, b: null, c: "C", d: null})
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithObjectInput' => '{"a":null,"b":null,"c":"C","d":null}']];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());

        // properly parses null value in list
        $doc = '
        {
          fieldWithObjectInput(input: {b: ["A",null,"C"], c: "C"})
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithObjectInput' => '{"b":["A",null,"C"],"c":"C"}']];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());

        // does not use incorrect value
        $doc = '
        {
          fieldWithObjectInput(input: ["foo", "bar", "baz"])
        }
        ';
        $ast = Parser::parse($doc);
        $result = Executor::execute($this->schema(), $ast)->toArray();

        $expected = [
            'data' => ['fieldWithObjectInput' => null],
            'errors' => [[
                'message' => 'Argument "input" got invalid value ["foo", "bar", "baz"].' . "\n" .
                    'Expected "TestInputObject", found not an object.',
                'path' => ['fieldWithObjectInput']
            ]]
        ];
        $this->assertArraySubset($expected, $result);

        // properly runs parseLiteral on complex scalar types
        $doc = '
        {
          fieldWithObjectInput(input: {c: "foo", d: "SerializedValue"})
        }
        ';
        $ast = Parser::parse($doc);
        $this->assertEquals(
            ['data' => ['fieldWithObjectInput' => '{"c":"foo","d":"DeserializedValue"}']],
            Executor::execute($this->schema(), $ast)->toArray()
        );
    }

    /**
     * @describe using variables
     */
    public function testUsingVariables()
    {
        // executes with complex input:
        $doc = '
        query q($input:TestInputObject) {
          fieldWithObjectInput(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $params = ['input' => ['a' => 'foo', 'b' => ['bar'], 'c' => 'baz']];
        $schema = $this->schema();

        $this->assertEquals(
            ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']],
            Executor::execute($schema, $ast, null, null, $params)->toArray()
        );

        // uses default value when not provided:
        $withDefaultsNode = Parser::parse('
          query q($input: TestInputObject = {a: "foo", b: ["bar"], c: "baz"}) {
            fieldWithObjectInput(input: $input)
          }
        ');

        $result = Executor::execute($this->schema(), $withDefaultsNode)->toArray();
        $expected = [
            'data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']
        ];
        $this->assertEquals($expected, $result);

        // properly parses single value to array:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => 'baz']];
        $this->assertEquals(
            ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']],
            Executor::execute($schema, $ast, null, null, $params)->toArray()
        );

        // executes with complex scalar input:
        $params = [ 'input' => [ 'c' => 'foo', 'd' => 'SerializedValue' ] ];
        $result = Executor::execute($schema, $ast, null, null, $params)->toArray();
        $expected = [
          'data' => [
            'fieldWithObjectInput' => '{"c":"foo","d":"DeserializedValue"}'
          ]
        ];
        $this->assertEquals($expected, $result);

        // errors on null for nested non-null:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => null]];
        $expected = FormattedError::create(
            'Variable "$input" got invalid value {"a":"foo","b":"bar","c":null}.'. "\n".
            'In field "c": Expected "String!", found null.',
            [new SourceLocation(2, 17)]
        );

        try {
            Executor::execute($schema, $ast, null, null, $params);
            $this->fail('Expected exception not thrown');
        } catch (Error $err) {
            $this->assertEquals($expected, Error::formatError($err));
        }

        // errors on incorrect type:
        $params = [ 'input' => 'foo bar' ];

        try {
            Executor::execute($schema, $ast, null, null, $params);
            $this->fail('Expected exception not thrown');
        } catch (Error $error) {
            $expected = FormattedError::create(
                'Variable "$input" got invalid value "foo bar".'."\n".
                'Expected "TestInputObject", found not an object.',
                [new SourceLocation(2, 17)]
            );
            $this->assertEquals($expected, Error::formatError($error));
        }

        // errors on omission of nested non-null:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar']];

        try {
            Executor::execute($schema, $ast, null, null, $params);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $expected = FormattedError::create(
                'Variable "$input" got invalid value {"a":"foo","b":"bar"}.'. "\n".
                'In field "c": Expected "String!", found null.',
                [new SourceLocation(2, 17)]
            );

            $this->assertEquals($expected, Error::formatError($e));
        }

        // errors on deep nested errors and with many errors
        $nestedDoc = '
          query q($input: TestNestedInputObject) {
            fieldWithNestedObjectInput(input: $input)
          }
        ';
        $nestedAst = Parser::parse($nestedDoc);
        $params = [ 'input' => [ 'na' => [ 'a' => 'foo' ] ] ];

        try {
            Executor::execute($schema, $nestedAst, null, null, $params);
            $this->fail('Expected exception not thrown');
        } catch (Error $error) {
            $expected = FormattedError::create(
                'Variable "$input" got invalid value {"na":{"a":"foo"}}.' . "\n" .
                'In field "na": In field "c": Expected "String!", found null.' . "\n" .
                'In field "nb": Expected "String!", found null.',
                [new SourceLocation(2, 19)]
            );
            $this->assertEquals($expected, Error::formatError($error));
        }

        // errors on addition of unknown input field
        $params = ['input' => [ 'a' => 'foo', 'b' => 'bar', 'c' => 'baz', 'd' => 'dog' ]];

        try {
            Executor::execute($schema, $ast, null, null, $params);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $expected = FormattedError::create(
                'Variable "$input" got invalid value {"a":"foo","b":"bar","c":"baz","d":"dog"}.'."\n".
                'In field "d": Expected type "ComplexScalar", found "dog".',
                [new SourceLocation(2, 17)]
            );
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    // Describe: Handles nullable scalars

    /**
     * @it allows nullable inputs to be omitted
     */
    public function testAllowsNullableInputsToBeOmitted()
    {
        $doc = '
      {
        fieldWithNullableStringInput
      }
        ';
        $ast = Parser::parse($doc);
        $expected = [
            'data' => ['fieldWithNullableStringInput' => null]
        ];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());
    }

    /**
     * @it allows nullable inputs to be omitted in a variable
     */
    public function testAllowsNullableInputsToBeOmittedInAVariable()
    {
        $doc = '
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => null]];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());
    }

    /**
     * @it allows nullable inputs to be omitted in an unlisted variable
     */
    public function testAllowsNullableInputsToBeOmittedInAnUnlistedVariable()
    {
        $doc = '
      query SetsNullable {
        fieldWithNullableStringInput(input: $value)
      }
      ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => null]];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());
    }

    /**
     * @it allows nullable inputs to be set to null in a variable
     */
    public function testAllowsNullableInputsToBeSetToNullInAVariable()
    {
        $doc = '
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => null]];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['value' => null])->toArray());
    }

    /**
     * @it allows nullable inputs to be set to a value in a variable
     */
    public function testAllowsNullableInputsToBeSetToAValueInAVariable()
    {
        $doc = '
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => '"a"']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, null, ['value' => 'a'])->toArray());
    }

    /**
     * @it allows nullable inputs to be set to a value directly
     */
    public function testAllowsNullableInputsToBeSetToAValueDirectly()
    {
        $doc = '
      {
        fieldWithNullableStringInput(input: "a")
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => '"a"']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());
    }


    // Describe: Handles non-nullable scalars

    /**
     * @it allows non-nullable inputs to be omitted given a default
     */
    public function testAllowsNonNullableInputsToBeOmittedGivenADefault()
    {
        $doc = '
        query SetsNonNullable($value: String = "default") {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = [
            'data' => ['fieldWithNonNullableStringInput' => '"default"']
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());

    }

    /**
     * @it does not allow non-nullable inputs to be omitted in a variable
     */
    public function testDoesntAllowNonNullableInputsToBeOmittedInAVariable()
    {
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);
        try {
            Executor::execute($this->schema(), $ast);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $expected = FormattedError::create(
                'Variable "$value" of required type "String!" was not provided.',
                [new SourceLocation(2, 31)]
            );
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    /**
     * @it does not allow non-nullable inputs to be set to null in a variable
     */
    public function testDoesNotAllowNonNullableInputsToBeSetToNullInAVariable()
    {
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);

        try {
            Executor::execute($this->schema(), $ast, null, null, ['value' => null]);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $expected = [
                'message' =>
                    'Variable "$value" got invalid value null.' . "\n".
                    'Expected "String!", found null.',
                'locations' => [['line' => 2, 'column' => 31]]
            ];
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    /**
     * @it allows non-nullable inputs to be set to a value in a variable
     */
    public function testAllowsNonNullableInputsToBeSetToAValueInAVariable()
    {
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNonNullableStringInput' => '"a"']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, null, ['value' => 'a'])->toArray());
    }

    /**
     * @it allows non-nullable inputs to be set to a value directly
     */
    public function testAllowsNonNullableInputsToBeSetToAValueDirectly()
    {
        $doc = '
      {
        fieldWithNonNullableStringInput(input: "a")
      }
      ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNonNullableStringInput' => '"a"']];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());
    }

    /**
     * @it reports error for missing non-nullable inputs
     */
    public function testReportsErrorForMissingNonNullableInputs()
    {
        $doc = '
      {
        fieldWithNonNullableStringInput
      }
        ';
        $ast = Parser::parse($doc);
        $expected = [
            'data' => ['fieldWithNonNullableStringInput' => null],
            'errors' => [[
                'message' => 'Argument "input" of required type "String!" was not provided.',
                'locations' => [ [ 'line' => 3, 'column' => 9 ] ],
                'path' => [ 'fieldWithNonNullableStringInput' ]
            ]]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());
    }

    /**
     * @it reports error for non-provided variables for non-nullable inputs
     */
    public function testReportsErrorForNonProvidedVariablesForNonNullableInputs()
    {
        // Note: this test would typically fail validation before encountering
        // this execution error, however for queries which previously validated
        // and are being run against a new schema which have introduced a breaking
        // change to make a formerly non-required argument required, this asserts
        // failure before allowing the underlying code to receive a non-null value.
        $doc = '
      {
        fieldWithNonNullableStringInput(input: $foo)
      }
        ';
        $ast = Parser::parse($doc);

        $expected = [
            'data' => ['fieldWithNonNullableStringInput' => null],
            'errors' => [[
                'message' =>
                    'Argument "input" of required type "String!" was provided the ' .
                    'variable "$foo" which was not provided a runtime value.',
                'locations' => [['line' => 3, 'column' => 48]],
                'path' => ['fieldWithNonNullableStringInput']
            ]]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());
    }

    // Describe: Handles lists and nullability

    /**
     * @it allows lists to be null
     */
    public function testAllowsListsToBeNull()
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['list' => null]];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['input' => null])->toArray());
    }

    /**
     * @it allows lists to contain values
     */
    public function testAllowsListsToContainValues()
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['list' => '["A"]']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, null, ['input' => ['A']])->toArray());
    }

    /**
     * @it allows lists to contain null
     */
    public function testAllowsListsToContainNull()
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['list' => '["A",null,"B"]']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, null, ['input' => ['A',null,'B']])->toArray());
    }

    /**
     * @it does not allow non-null lists to be null
     */
    public function testDoesNotAllowNonNullListsToBeNull()
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = FormattedError::create(
            'Variable "$input" got invalid value null.' . "\n" .
            'Expected "[String]!", found null.',
            [new SourceLocation(2, 17)]
        );

        try {
            $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, null, ['input' => null])->toArray());
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    /**
     * @it allows non-null lists to contain values
     */
    public function testAllowsNonNullListsToContainValues()
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['nnList' => '["A"]']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, null, ['input' => 'A'])->toArray());
    }

    /**
     * @it allows non-null lists to contain null
     */
    public function testAllowsNonNullListsToContainNull()
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['nnList' => '["A",null,"B"]']];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, null, ['input' => ['A',null,'B']])->toArray());
    }

    /**
     * @it allows lists of non-nulls to be null
     */
    public function testAllowsListsOfNonNullsToBeNull()
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['listNN' => null]];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['input' => null])->toArray());
    }

    /**
     * @it allows lists of non-nulls to contain values
     */
    public function testAllowsListsOfNonNullsToContainValues()
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['listNN' => '["A"]']];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, null, ['input' => 'A'])->toArray());
    }

    /**
     * @it does not allow lists of non-nulls to contain null
     */
    public function testDoesNotAllowListsOfNonNullsToContainNull()
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = FormattedError::create(
            'Variable "$input" got invalid value ["A",null,"B"].' . "\n" .
            'In element #1: Expected "String!", found null.',
            [new SourceLocation(2, 17)]
        );

        try {
            Executor::execute($this->schema(), $ast, null, null, ['input' => ['A', null, 'B']]);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    /**
     * @it does not allow non-null lists of non-nulls to be null
     */
    public function testDoesNotAllowNonNullListsOfNonNullsToBeNull()
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = FormattedError::create(
            'Variable "$input" got invalid value null.' . "\n" .
            'Expected "[String!]!", found null.',
            [new SourceLocation(2, 17)]
        );
        try {
            Executor::execute($this->schema(), $ast, null, null, ['input' => null]);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    /**
     * @it allows non-null lists of non-nulls to contain values
     */
    public function testAllowsNonNullListsOfNonNullsToContainValues()
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['nnListNN' => '["A"]']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, null, ['input' => ['A']])->toArray());
    }

    /**
     * @it does not allow non-null lists of non-nulls to contain null
     */
    public function testDoesNotAllowNonNullListsOfNonNullsToContainNull()
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = FormattedError::create(
            'Variable "$input" got invalid value ["A",null,"B"].'."\n".
            'In element #1: Expected "String!", found null.',
            [new SourceLocation(2, 17)]
        );
        try {
            Executor::execute($this->schema(), $ast, null, null, ['input' => ['A', null, 'B']]);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    /**
     * @it does not allow invalid types to be used as values
     */
    public function testDoesNotAllowInvalidTypesToBeUsedAsValues()
    {
        $doc = '
        query q($input: TestType!) {
          fieldWithObjectInput(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $vars = [ 'input' => [ 'list' => [ 'A', 'B' ] ] ];

        try {
            Executor::execute($this->schema(), $ast, null, null, $vars);
            $this->fail('Expected exception not thrown');
        } catch (Error $error) {
            $expected = [
                'message' =>
                    'Variable "$input" expected value of type "TestType!" which cannot ' .
                    'be used as an input type.',
                'locations' => [['line' => 2, 'column' => 25]]
            ];
            $this->assertEquals($expected, Error::formatError($error));
        }
    }

    /**
     * @it does not allow unknown types to be used as values
     */
    public function testDoesNotAllowUnknownTypesToBeUsedAsValues()
    {
        $doc = '
        query q($input: UnknownType!) {
          fieldWithObjectInput(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $vars = ['input' => 'whoknows'];

        try {
            Executor::execute($this->schema(), $ast, null, null, $vars);
            $this->fail('Expected exception not thrown');
        } catch (Error $error) {
            $expected = FormattedError::create(
                'Variable "$input" expected value of type "UnknownType!" which ' .
                'cannot be used as an input type.',
                [new SourceLocation(2, 25)]
            );
            $this->assertEquals($expected, Error::formatError($error));
        }
    }

    // Describe: Execute: Uses argument default values
    /**
     * @it when no argument provided
     */
    public function testWhenNoArgumentProvided()
    {
        $ast = Parser::parse('{
        fieldWithDefaultArgumentValue
        }');

        $this->assertEquals(
            ['data' => ['fieldWithDefaultArgumentValue' => '"Hello World"']],
            Executor::execute($this->schema(), $ast)->toArray()
        );
    }

    /**
     * @it when omitted variable provided
     */
    public function testWhenOmittedVariableProvided()
    {
        $ast = Parser::parse('query optionalVariable($optional: String) {
            fieldWithDefaultArgumentValue(input: $optional)
        }');

        $this->assertEquals(
            ['data' => ['fieldWithDefaultArgumentValue' => '"Hello World"']],
            Executor::execute($this->schema(), $ast)->toArray()
        );
    }

    /**
     * @it not when argument cannot be coerced
     */
    public function testNotWhenArgumentCannotBeCoerced()
    {
        $ast = Parser::parse('{
            fieldWithDefaultArgumentValue(input: WRONG_TYPE)
        }');

        $expected = [
            'data' => ['fieldWithDefaultArgumentValue' => null],
            'errors' => [[
                'message' =>
                    'Argument "input" got invalid value WRONG_TYPE.' . "\n" .
                    'Expected type "String", found WRONG_TYPE.',
                'locations' => [ [ 'line' => 2, 'column' => 50 ] ],
                'path' => [ 'fieldWithDefaultArgumentValue' ]
            ]]
        ];

        $this->assertEquals(
            $expected,
            Executor::execute($this->schema(), $ast)->toArray()
        );
    }


    public function schema()
    {
        $ComplexScalarType = ComplexScalar::create();

        $TestInputObject = new InputObjectType([
            'name' => 'TestInputObject',
            'fields' => [
                'a' => ['type' => Type::string()],
                'b' => ['type' => Type::listOf(Type::string())],
                'c' => ['type' => Type::nonNull(Type::string())],
                'd' => ['type' => $ComplexScalarType],
            ]
        ]);

        $TestNestedInputObject = new InputObjectType([
            'name' => 'TestNestedInputObject',
            'fields' => [
                'na' => [ 'type' => Type::nonNull($TestInputObject) ],
                'nb' => [ 'type' => Type::nonNull(Type::string()) ],
            ],
        ]);

        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'fieldWithObjectInput' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => $TestInputObject]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? json_encode($args['input']) : null;
                    }
                ],
                'fieldWithNullableStringInput' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::string()]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ?  json_encode($args['input']) : null;
                    }
                ],
                'fieldWithNonNullableStringInput' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::nonNull(Type::string())]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? json_encode($args['input']) : null;
                    }
                ],
                'fieldWithDefaultArgumentValue' => [
                    'type' => Type::string(),
                    'args' => [ 'input' => [ 'type' => Type::string(), 'defaultValue' => 'Hello World' ]],
                    'resolve' => function($_, $args) {
                        return isset($args['input']) ? json_encode($args['input']) : null;
                    }
                ],
                'fieldWithNestedInputObject' => [
                    'type' => Type::string(),
                    'args' => [
                        'input' => [
                            'type' => $TestNestedInputObject,
                            'defaultValue' => 'Hello World'
                        ]
                    ],
                    'resolve' => function($_, $args) {
                        return isset($args['input']) ? json_encode($args['input']) : null;
                    }
                ],
                'list' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::listOf(Type::string())]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? json_encode($args['input']) : null;
                    }
                ],
                'nnList' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::nonNull(Type::listOf(Type::string()))]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? json_encode($args['input']) : null;
                    }
                ],
                'listNN' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::listOf(Type::nonNull(Type::string()))]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? json_encode($args['input']) : null;
                    }
                ],
                'nnListNN' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string())))]],
                    'resolve' => function ($_, $args) {
                        return isset($args['input']) ? json_encode($args['input']) : null;
                    }
                ],
            ]
        ]);

        $schema = new Schema(['query' => $TestType]);
        return $schema;
    }
}
