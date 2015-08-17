<?php
namespace GraphQL\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Error;
use GraphQL\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;

class VariablesTest extends \PHPUnit_Framework_TestCase
{
    // Execute: Handles inputs
    // Handles objects and nullability

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
    }

    public function testDoesNotUseIncorrectValue()
    {
        $doc = '
        {
          fieldWithObjectInput(input: ["foo", "bar", "baz"])
        }
        ';
        $ast = Parser::parse($doc);
        $result = Executor::execute($this->schema(), $ast)->toArray();

        $expected = [
            'data' => ['fieldWithObjectInput' => null]
        ];
        $this->assertEquals($expected, $result);
    }

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
            Executor::execute($schema, $ast, null, $params)->toArray()
        );

        // uses default value when not provided:
        $withDefaultsAST = Parser::parse('
          query q($input: TestInputObject = {a: "foo", b: ["bar"], c: "baz"}) {
            fieldWithObjectInput(input: $input)
          }
        ');

        $result = Executor::execute($this->schema(), $withDefaultsAST)->toArray();
        $expected = [
            'data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']
        ];
        $this->assertEquals($expected, $result);


        // properly parses single value to array:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => 'baz']];
        $this->assertEquals(
            ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']],
            Executor::execute($schema, $ast, null, $params)->toArray()
        );

        // executes with complex scalar input:
        $params = [ 'input' => [ 'c' => 'foo', 'd' => 'SerializedValue' ] ];
        $result = Executor::execute($schema, $ast, null, $params)->toArray();
        $expected = [
          'data' => [
            'fieldWithObjectInput' => '{"c":"foo","d":"DeserializedValue"}'
          ]
        ];
        $this->assertEquals($expected, $result);

        // errors on null for nested non-null:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => null]];
        $expected = FormattedError::create(
            'Variable $input expected value of type ' .
            'TestInputObject but got: ' .
            '{"a":"foo","b":"bar","c":null}.',
            [new SourceLocation(2, 17)]
        );

        try {
            Executor::execute($schema, $ast, null, $params);
            $this->fail('Expected exception not thrown');
        } catch (Error $err) {
            $this->assertEquals($expected, Error::formatError($err));
        }

        // errors on incorrect type:
        $params = [ 'input' => 'foo bar' ];

        try {
            Executor::execute($schema, $ast, null, $params);
            $this->fail('Expected exception not thrown');
        } catch (Error $error) {
            $expected = FormattedError::create(
                'Variable $input expected value of type TestInputObject but got: "foo bar".',
                [new SourceLocation(2, 17)]
            );
            $this->assertEquals($expected, Error::formatError($error));
        }

        // errors on omission of nested non-null:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar']];

        try {
            Executor::execute($schema, $ast, null, $params);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $expected = FormattedError::create(
                'Variable $input expected value of type ' .
                'TestInputObject but got: {"a":"foo","b":"bar"}.',
                [new SourceLocation(2, 17)]
            );

            $this->assertEquals($expected, Error::formatError($e));
        }

        // errors on addition of unknown input field
        $params = ['input' => [ 'a' => 'foo', 'b' => 'bar', 'c' => 'baz', 'd' => 'dog' ]];

        try {
            Executor::execute($schema, $ast, null, $params);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $expected = FormattedError::create(
                'Variable $input expected value of type TestInputObject but ' .
                'got: {"a":"foo","b":"bar","c":"baz","d":"dog"}.',
                [new SourceLocation(2, 17)]
            );
            $this->assertEquals($expected, Error::formatError($e));
        }
    }


    // Handles nullable scalars
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

    public function testAllowsNullableInputsToBeSetToAValueInAVariable()
    {
        $doc = '
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => '"a"']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['value' => 'a'])->toArray());
    }

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


    // Handles non-nullable scalars
    public function testDoesntAllowNonNullableInputsToBeOmittedInAVariable()
    {
        // does not allow non-nullable inputs to be omitted in a variable
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
                'Variable $value expected value of type String! but got: null.',
                [new SourceLocation(2, 31)]
            );
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    public function testDoesNotAllowNonNullableInputsToBeSetToNullInAVariable()
    {
        // does not allow non-nullable inputs to be set to null in a variable
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);

        try {
            Executor::execute($this->schema(), $ast, null, ['value' => null]);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $expected = FormattedError::create(
                'Variable $value expected value of type String! but got: null.',
                [new SourceLocation(2, 31)]
            );
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    public function testAllowsNonNullableInputsToBeSetToAValueInAVariable()
    {
        $doc = '
        query SetsNonNullable($value: String!) {
          fieldWithNonNullableStringInput(input: $value)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNonNullableStringInput' => '"a"']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['value' => 'a'])->toArray());
    }

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

    public function testPassesAlongNullForNonNullableInputsIfExplcitlySetInTheQuery()
    {
        $doc = '
      {
        fieldWithNonNullableStringInput
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNonNullableStringInput' => null]];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast)->toArray());
    }

    // Handles lists and nullability
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

    public function testAllowsListsToContainValues()
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['list' => '["A"]']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['input' => ['A']])->toArray());
    }

    public function testAllowsListsToContainNull()
    {
        $doc = '
        query q($input:[String]) {
          list(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['list' => '["A",null,"B"]']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['input' => ['A',null,'B']])->toArray());
    }

    public function testDoesNotAllowNonNullListsToBeNull()
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = FormattedError::create(
            'Variable $input expected value of type [String]! but got: null.',
            [new SourceLocation(2, 17)]
        );

        try {
            $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['input' => null])->toArray());
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    public function testAllowsNonNullListsToContainValues()
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['nnList' => '["A"]']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['input' => 'A'])->toArray());
    }

    public function testAllowsNonNullListsToContainNull()
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['nnList' => '["A",null,"B"]']];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['input' => ['A',null,'B']])->toArray());
    }

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

    public function testAllowsListsOfNonNullsToContainValues()
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['listNN' => '["A"]']];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['input' => 'A'])->toArray());
    }

    public function testDoesNotAllowListsOfNonNullsToContainNull()
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = FormattedError::create(
            'Variable $input expected value of type [String!] but got: ["A",null,"B"].',
            [new SourceLocation(2, 17)]
        );

        try {
            Executor::execute($this->schema(), $ast, null, ['input' => ['A', null, 'B']]);
            $this->fail('Expected exception not thrown');
        } catch (Error $e) {
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    public function testDoesNotAllowNonNullListsOfNonNullsToBeNull()
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = FormattedError::create(
            'Variable $input expected value of type [String!]! but got: null.',
            [new SourceLocation(2, 17)]
        );
        try {
            Executor::execute($this->schema(), $ast, null, ['input' => null]);
        } catch (Error $e) {
            $this->assertEquals($expected, Error::formatError($e));
        }
    }

    public function testAllowsNonNullListsOfNonNullsToContainValues()
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['nnListNN' => '["A"]']];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, null, ['input' => ['A']])->toArray());
    }

    public function testDoesNotAllowNonNullListsOfNonNullsToContainNull()
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = FormattedError::create(
            'Variable $input expected value of type [String!]! but got: ["A",null,"B"].',
            [new SourceLocation(2, 17)]
        );
        try {
            Executor::execute($this->schema(), $ast, null, ['input' => ['A', null, 'B']]);
        } catch (Error $e) {
            $this->assertEquals($expected, Error::formatError($e));
        }
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

        $schema = new Schema($TestType);
        return $schema;
    }
}
