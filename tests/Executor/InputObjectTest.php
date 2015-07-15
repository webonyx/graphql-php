<?php
namespace GraphQL\Executor;

use GraphQL\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class InputObjectTest extends \PHPUnit_Framework_TestCase
{
    // Execute: Handles input objects
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
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast));

        // properly coerces single value to array:
        $doc = '
        {
          fieldWithObjectInput(input: {a: "foo", b: "bar", c: "baz"})
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']];

        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast));
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
            Executor::execute($schema, null, $ast, null, $params)
        );

        // properly coerces single value to array:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => 'baz']];
        $this->assertEquals(
            ['data' => ['fieldWithObjectInput' => '{"a":"foo","b":["bar"],"c":"baz"}']],
            Executor::execute($schema, null, $ast, null, $params)
        );

        // errors on null for nested non-null:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar', 'c' => null]];
        $expected = [
            'data' => null,
            'errors' => [
                new FormattedError(
                    'Variable $input expected value of type ' .
                    'TestInputObject but got: ' .
                    '{"a":"foo","b":"bar","c":null}.',
                    [new SourceLocation(2, 17)]
                )
            ]
        ];

        $this->assertEquals($expected, Executor::execute($schema, null, $ast, null, $params));

        // errors on omission of nested non-null:
        $params = ['input' => ['a' => 'foo', 'b' => 'bar']];
        $expected = [
            'data' => null,
            'errors' => [
                new FormattedError(
                    'Variable $input expected value of type ' .
                    'TestInputObject but got: {"a":"foo","b":"bar"}.',
                    [new SourceLocation(2, 17)]
                )
            ]
        ];

        $this->assertEquals($expected, Executor::execute($schema, null, $ast, null, $params));
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
            'data' => ['fieldWithNullableStringInput' => 'null']
        ];

        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast));
    }

    public function testAllowsNullableInputsToBeOmittedInAVariable()
    {
        $doc = '
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => 'null']];

        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast));
    }

    public function testAllowsNullableInputsToBeOmittedInAnUnlistedVariable()
    {
        $doc = '
      query SetsNullable {
        fieldWithNullableStringInput(input: $value)
      }
      ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => 'null']];
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast));
    }

    public function testAllowsNullableInputsToBeSetToNullInAVariable()
    {
        $doc = '
      query SetsNullable($value: String) {
        fieldWithNullableStringInput(input: $value)
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => 'null']];

        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['value' => null]));
    }

    public function testAllowsNullableInputsToBeSetToNullDirectly()
    {
        $doc = '
      {
        fieldWithNullableStringInput(input: null)
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNullableStringInput' => 'null']];
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast));
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
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['value' => 'a']));
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
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast));
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
        $expected = [
            'data' => null,
            'errors' => [
                new FormattedError(
                    'Variable $value expected value of type String! but got: null.',
                    [new SourceLocation(2, 31)]
                )
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast));
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
        $expected = [
            'data' => null,
            'errors' => [
                new FormattedError(
                    'Variable $value expected value of type String! but got: null.',
                    [new SourceLocation(2, 31)]
                )
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['value' => null]));
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
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['value' => 'a']));
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

        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast));
    }

    public function testPassesAlongNullForNonNullableInputsIfExplcitlySetInTheQuery()
    {
        $doc = '
      {
        fieldWithNonNullableStringInput
      }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['fieldWithNonNullableStringInput' => 'null']];
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast));
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
        $expected = ['data' => ['list' => 'null']];

        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => null]));
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
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => ['A']]));
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
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => ['A',null,'B']]));
    }

    public function testDoesNotAllowNonNullListsToBeNull()
    {
        $doc = '
        query q($input:[String]!) {
          nnList(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = [
            'data' => null,
            'errors' => [
                new FormattedError(
                    'Variable $input expected value of type [String]! but got: null.',
                    [new SourceLocation(2, 17)]
                )
            ]
        ];

        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => null]));
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
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => 'A']));
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

        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => ['A',null,'B']]));
    }

    public function testAllowsListsOfNonNullsToBeNull()
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = ['data' => ['listNN' => 'null']];
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => null]));
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

        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => 'A']));
    }

    public function testDoesNotAllowListsOfNonNullsToContainNull()
    {
        $doc = '
        query q($input:[String!]) {
          listNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = [
            'data' => null,
            'errors' => [
                new FormattedError(
                    'Variable $input expected value of type [String!] but got: ["A",null,"B"].',
                    [new SourceLocation(2, 17)]
                )
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => ['A', null, 'B']]));
    }

    public function testDoesNotAllowNonNullListsOfNonNullsToBeNull()
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = [
            'data' => null,
            'errors' => [
                new FormattedError(
                    'Variable $input expected value of type [String!]! but got: null.',
                    [new SourceLocation(2, 17)]
                )
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => null]));
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
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => ['A']]));
    }

    public function testDoesNotAllowNonNullListsOfNonNullsToContainNull()
    {
        $doc = '
        query q($input:[String!]!) {
          nnListNN(input: $input)
        }
        ';
        $ast = Parser::parse($doc);
        $expected = [
            'data' => null,
            'errors' => [
                new FormattedError(
                    'Variable $input expected value of type [String!]! but got: ["A",null,"B"].',
                    [new SourceLocation(2, 17)]
                )
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), null, $ast, null, ['input' => ['A',null,'B']]));
    }


    public function schema()
    {
        $TestInputObject = new InputObjectType([
            'name' => 'TestInputObject',
            'fields' => [
                'a' => ['type' => Type::string()],
                'b' => ['type' => Type::listOf(Type::string())],
                'c' => ['type' => Type::nonNull(Type::string())]
            ]
        ]);

        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'fieldWithObjectInput' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => $TestInputObject]],
                    'resolve' => function ($_, $args) {
                        return json_encode($args['input']);
                    }
                ],
                'fieldWithNullableStringInput' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::string()]],
                    'resolve' => function ($_, $args) {
                        return json_encode($args['input']);
                    }
                ],
                'fieldWithNonNullableStringInput' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::nonNull(Type::string())]],
                    'resolve' => function ($_, $args) {
                        return json_encode($args['input']);
                    }
                ],
                'list' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::listOf(Type::string())]],
                    'resolve' => function ($_, $args) {
                        return json_encode($args['input']);
                    }
                ],
                'nnList' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::nonNull(Type::listOf(Type::string()))]],
                    'resolve' => function ($_, $args) {
                        return json_encode($args['input']);
                    }
                ],
                'listNN' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::listOf(Type::nonNull(Type::string()))]],
                    'resolve' => function ($_, $args) {
                        return json_encode($args['input']);
                    }
                ],
                'nnListNN' => [
                    'type' => Type::string(),
                    'args' => ['input' => ['type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string())))]],
                    'resolve' => function ($_, $args) {
                        return json_encode($args['input']);
                    }
                ],
            ]
        ]);

        $schema = new Schema($TestType);
        return $schema;
    }
}
