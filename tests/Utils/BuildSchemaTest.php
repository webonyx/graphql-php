<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Type\Definition\Directive;
use PHPUnit\Framework\TestCase;

class BuildSchemaTest extends TestCase
{
    // Describe: Schema Builder

    private function cycleOutput($body, $options = [])
    {
        $ast = Parser::parse($body);
        $schema = BuildSchema::buildAST($ast, null, $options);
        return "\n" . SchemaPrinter::doPrint($schema, $options);
    }

    /**
     * @see it('can use built schema for limited execution')
     */
    public function testUseBuiltSchemaForLimitedExecution() : void
    {
        $schema = BuildSchema::buildAST(Parser::parse('
            type Query {
                str: String
            }
        '));

        $result = GraphQL::executeQuery($schema, '{ str }', ['str' => 123]);
        $this->assertEquals(['str' => 123], $result->toArray(true)['data']);
    }

    /**
     * @see it('can build a schema directly from the source')
     */
    public function testBuildSchemaDirectlyFromSource() : void
    {
        $schema = BuildSchema::build("
            type Query {
                add(x: Int, y: Int): Int
            }
        ");

        $root = [
            'add' => function ($root, $args) {
                return $args['x'] + $args['y'];
            }
        ];

        $result = GraphQL::executeQuery(
            $schema,
            '{ add(x: 34, y: 55) }',
            $root
        );
        $this->assertEquals(['data' => ['add' => 89]], $result->toArray(true));
    }

    /**
     * @see it('Simple Type')
     */
    public function testSimpleType() : void
    {
        $body = '
type HelloScalars {
  str: String
  int: Int
  float: Float
  id: ID
  bool: Boolean
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('With directives')
     */
    public function testWithDirectives() : void
    {
        $body = '
directive @foo(arg: Int) on FIELD

type Query {
  str: String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Supports descriptions')
     */
    public function testSupportsDescriptions() : void
    {
      $body = '
"""This is a directive"""
directive @foo(
  """It has an argument"""
  arg: Int
) on FIELD

"""With an enum"""
enum Color {
  RED

  """Not a creative color"""
  GREEN
  BLUE
}

"""What a great type"""
type Query {
  """And a field to boot"""
  str: String
}
';

        $output = $this->cycleOutput($body);
        $this->assertEquals($body, $output);
    }

    /**
     * @see it('Supports option for comment descriptions')
     */
    public function testSupportsOptionForCommentDescriptions() : void
    {
        $body = '
# This is a directive
directive @foo(
  # It has an argument
  arg: Int
) on FIELD

# With an enum
enum Color {
  RED

  # Not a creative color
  GREEN
  BLUE
}

# What a great type
type Query {
  # And a field to boot
  str: String
}
';
        $output = $this->cycleOutput($body, [ 'commentDescriptions' => true ]);
        $this->assertEquals($body, $output);
    }

    /**
     * @see it('Maintains @skip & @include')
     */
    public function testMaintainsSkipAndInclude() : void
    {
        $body = '
type Query {
  str: String
}
';
        $schema = BuildSchema::buildAST(Parser::parse($body));
        $this->assertEquals(count($schema->getDirectives()), 3);
        $this->assertEquals($schema->getDirective('skip'), Directive::skipDirective());
        $this->assertEquals($schema->getDirective('include'), Directive::includeDirective());
        $this->assertEquals($schema->getDirective('deprecated'), Directive::deprecatedDirective());
    }

    /**
     * @see it('Overriding directives excludes specified')
     */
    public function testOverridingDirectivesExcludesSpecified() : void
    {
        $body = '
directive @skip on FIELD
directive @include on FIELD
directive @deprecated on FIELD_DEFINITION

type Query {
  str: String
}
    ';
        $schema = BuildSchema::buildAST(Parser::parse($body));
        $this->assertEquals(count($schema->getDirectives()), 3);
        $this->assertNotEquals($schema->getDirective('skip'), Directive::skipDirective());
        $this->assertNotEquals($schema->getDirective('include'), Directive::includeDirective());
        $this->assertNotEquals($schema->getDirective('deprecated'), Directive::deprecatedDirective());
    }

    /**
     * @see it('Adding directives maintains @skip & @include')
     */
    public function testAddingDirectivesMaintainsSkipAndInclude() : void
    {
        $body = '
      directive @foo(arg: Int) on FIELD

      type Query {
        str: String
      }
    ';
        $schema = BuildSchema::buildAST(Parser::parse($body));
        $this->assertCount(4, $schema->getDirectives());
        $this->assertNotEquals(null, $schema->getDirective('skip'));
        $this->assertNotEquals(null, $schema->getDirective('include'));
        $this->assertNotEquals(null, $schema->getDirective('deprecated'));
    }

    /**
     * @see it('Type modifiers')
     */
    public function testTypeModifiers() : void
    {
        $body = '
type HelloScalars {
  nonNullStr: String!
  listOfStrs: [String]
  listOfNonNullStrs: [String!]
  nonNullListOfStrs: [String]!
  nonNullListOfNonNullStrs: [String!]!
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Recursive type')
     */
    public function testRecursiveType() : void
    {
        $body = '
type Query {
  str: String
  recurse: Query
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Two types circular')
     */
    public function testTwoTypesCircular() : void
    {
        $body = '
schema {
  query: TypeOne
}

type TypeOne {
  str: String
  typeTwo: TypeTwo
}

type TypeTwo {
  str: String
  typeOne: TypeOne
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Single argument field')
     */
    public function testSingleArgumentField() : void
    {
        $body = '
type Query {
  str(int: Int): String
  floatToStr(float: Float): String
  idToStr(id: ID): String
  booleanToStr(bool: Boolean): String
  strToStr(bool: String): String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Simple type with multiple arguments')
     */
    public function testSimpleTypeWithMultipleArguments() : void
    {
        $body = '
type Query {
  str(int: Int, bool: Boolean): String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Simple type with interface')
     */
    public function testSimpleTypeWithInterface() : void
    {
        $body = '
type Query implements WorldInterface {
  str: String
}

interface WorldInterface {
  str: String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Simple output enum')
     */
    public function testSimpleOutputEnum() : void
    {
        $body = '
enum Hello {
  WORLD
}

type Query {
  hello: Hello
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Simple input enum')
     */
    public function testSimpleInputEnum() : void
    {
        $body = '
enum Hello {
  WORLD
}

type Query {
  str(hello: Hello): String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($body, $output);
    }

    /**
     * @see it('Multiple value enum')
     */
    public function testMultipleValueEnum() : void
    {
        $body = '
enum Hello {
  WO
  RLD
}

type Query {
  hello: Hello
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Simple Union')
     */
    public function testSimpleUnion() : void
    {
        $body = '
union Hello = World

type Query {
  hello: Hello
}

type World {
  str: String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Multiple Union')
     */
    public function testMultipleUnion() : void
    {
        $body = '
union Hello = WorldOne | WorldTwo

type Query {
  hello: Hello
}

type WorldOne {
  str: String
}

type WorldTwo {
  str: String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Specifying Union type using __typename')
     */
    public function testSpecifyingUnionTypeUsingTypename() : void
    {
        $schema = BuildSchema::buildAST(Parser::parse('
            type Query {
              fruits: [Fruit]
            }
            
            union Fruit = Apple | Banana
            
            type Apple {
              color: String
            }
            
            type Banana {
              length: Int
            }
        '));
        $query = '
            {
              fruits {
                ... on Apple {
                  color
                }
                ... on Banana {
                  length
                }
              }
            }
        ';
        $root = [
            'fruits' => [
                [
                    'color' => 'green',
                    '__typename' => 'Apple',
                ],
                [
                    'length' => 5,
                    '__typename' => 'Banana',
                ]
            ]
        ];
        $expected = [
            'data' => [
                'fruits' => [
                    ['color' => 'green'],
                    ['length' => 5],
                ]
            ]
        ];

        $result = GraphQL::executeQuery($schema, $query, $root);
        $this->assertEquals($expected, $result->toArray(true));
    }

    /**
     * @see it('Specifying Interface type using __typename')
     */
    public function testSpecifyingInterfaceUsingTypename() : void
    {
        $schema = BuildSchema::buildAST(Parser::parse('
            type Query {
              characters: [Character]
            }
            
            interface Character {
              name: String!
            }
            
            type Human implements Character {
              name: String!
              totalCredits: Int
            }
            
            type Droid implements Character {
              name: String!
              primaryFunction: String
            }
        '));
        $query = '
            {
              characters {
                name
                ... on Human {
                  totalCredits
                }
                ... on Droid {
                  primaryFunction
                }
              }
            }
        ';
        $root = [
            'characters' => [
                [
                    'name' => 'Han Solo',
                    'totalCredits' => 10,
                    '__typename' => 'Human',
                ],
                [
                    'name' => 'R2-D2',
                    'primaryFunction' => 'Astromech',
                    '__typename' => 'Droid',
                ]
            ]
        ];
        $expected = [
            'data' => [
                'characters' => [
                    ['name' => 'Han Solo', 'totalCredits' => 10],
                    ['name' => 'R2-D2', 'primaryFunction' => 'Astromech'],
                ]
            ]
        ];

        $result = GraphQL::executeQuery($schema, $query, $root);
        $this->assertEquals($expected, $result->toArray(true));
    }

    /**
     * @see it('Custom Scalar')
     */
    public function testCustomScalar() : void
    {
        $body = '
scalar CustomScalar

type Query {
  customScalar: CustomScalar
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Input Object')
     */
    public function testInputObject() : void
    {
        $body = '
input Input {
  int: Int
}

type Query {
  field(in: Input): String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Simple argument field with default')
     */
    public function testSimpleArgumentFieldWithDefault() : void
    {
        $body = '
type Query {
  str(int: Int = 2): String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Custom scalar argument field with default')
     */
    public function testCustomScalarArgumentFieldWithDefault() : void
    {
        $body = '
scalar CustomScalar

type Query {
  str(int: CustomScalar = 2): String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Simple type with mutation')
     */
    public function testSimpleTypeWithMutation() : void
    {
        $body = '
schema {
  query: HelloScalars
  mutation: Mutation
}

type HelloScalars {
  str: String
  int: Int
  bool: Boolean
}

type Mutation {
  addHelloScalars(str: String, int: Int, bool: Boolean): HelloScalars
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Simple type with subscription')
     */
    public function testSimpleTypeWithSubscription() : void
    {
        $body = '
schema {
  query: HelloScalars
  subscription: Subscription
}

type HelloScalars {
  str: String
  int: Int
  bool: Boolean
}

type Subscription {
  subscribeHelloScalars(str: String, int: Int, bool: Boolean): HelloScalars
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Unreferenced type implementing referenced interface')
     */
    public function testUnreferencedTypeImplementingReferencedInterface() : void
    {
        $body = '
type Concrete implements Iface {
  key: String
}

interface Iface {
  key: String
}

type Query {
  iface: Iface
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Unreferenced type implementing referenced union')
     */
    public function testUnreferencedTypeImplementingReferencedUnion() : void
    {
        $body = '
type Concrete {
  key: String
}

type Query {
  union: Union
}

union Union = Concrete
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @see it('Supports @deprecated')
     */
    public function testSupportsDeprecated() : void
    {
        $body = '
enum MyEnum {
  VALUE
  OLD_VALUE @deprecated
  OTHER_VALUE @deprecated(reason: "Terrible reasons")
}

type Query {
  field1: String @deprecated
  field2: Int @deprecated(reason: "Because I said so")
  enum: MyEnum
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);

        $ast = Parser::parse($body);
        $schema = BuildSchema::buildAST($ast);

        /** @var EnumType $myEnum */
        $myEnum = $schema->getType('MyEnum');

        $value = $myEnum->getValue('VALUE');
        $this->assertFalse($value->isDeprecated());

        $oldValue = $myEnum->getValue('OLD_VALUE');
        $this->assertTrue($oldValue->isDeprecated());
        $this->assertEquals('No longer supported', $oldValue->deprecationReason);

        $otherValue = $myEnum->getValue('OTHER_VALUE');
        $this->assertTrue($otherValue->isDeprecated());
        $this->assertEquals('Terrible reasons', $otherValue->deprecationReason);

        $rootFields = $schema->getType('Query')->getFields();
        $this->assertEquals($rootFields['field1']->isDeprecated(), true);
        $this->assertEquals($rootFields['field1']->deprecationReason, 'No longer supported');

        $this->assertEquals($rootFields['field2']->isDeprecated(), true);
        $this->assertEquals($rootFields['field2']->deprecationReason, 'Because I said so');
    }

    /**
     * @see it('Correctly assign AST nodes')
     */
    public function testCorrectlyAssignASTNodes() : void
    {
        $schemaAST = Parser::parse('
      schema {
        query: Query
      }

      type Query {
        testField(testArg: TestInput): TestUnion
      }

      input TestInput {
        testInputField: TestEnum
      }

      enum TestEnum {
        TEST_VALUE
      }

      union TestUnion = TestType

      interface TestInterface {
        interfaceField: String
      }

      type TestType implements TestInterface {
        interfaceField: String
      }
      
      scalar TestScalar

      directive @test(arg: TestScalar) on FIELD
    ');
        $schema = BuildSchema::buildAST($schemaAST);
        /** @var ObjectType $query */
        $query = $schema->getType('Query');
        $testInput = $schema->getType('TestInput');
        $testEnum = $schema->getType('TestEnum');
        $testUnion = $schema->getType('TestUnion');
        $testInterface = $schema->getType('TestInterface');
        $testType = $schema->getType('TestType');
        $testScalar = $schema->getType('TestScalar');
        $testDirective = $schema->getDirective('test');

        $restoredIDL = SchemaPrinter::doPrint(BuildSchema::build(
            Printer::doPrint($schema->getAstNode()) . "\n" .
            Printer::doPrint($query->astNode) . "\n" .
            Printer::doPrint($testInput->astNode) . "\n" .
            Printer::doPrint($testEnum->astNode) . "\n" .
            Printer::doPrint($testUnion->astNode) . "\n" .
            Printer::doPrint($testInterface->astNode) . "\n" .
            Printer::doPrint($testType->astNode) . "\n" .
            Printer::doPrint($testScalar->astNode) . "\n" .
            Printer::doPrint($testDirective->astNode)
        ));

        $this->assertEquals($restoredIDL, SchemaPrinter::doPrint($schema));

        $testField = $query->getField('testField');
        $this->assertEquals('testField(testArg: TestInput): TestUnion', Printer::doPrint($testField->astNode));
        $this->assertEquals('testArg: TestInput', Printer::doPrint($testField->args[0]->astNode));
        $this->assertEquals('testInputField: TestEnum', Printer::doPrint($testInput->getField('testInputField')->astNode));
        $this->assertEquals('TEST_VALUE', Printer::doPrint($testEnum->getValue('TEST_VALUE')->astNode));
        $this->assertEquals('interfaceField: String', Printer::doPrint($testInterface->getField('interfaceField')->astNode));
        $this->assertEquals('interfaceField: String', Printer::doPrint($testType->getField('interfaceField')->astNode));
        $this->assertEquals('arg: TestScalar', Printer::doPrint($testDirective->args[0]->astNode));
    }

    /**
     * @see it('Root operation types with custom names')
     */
    public function testRootOperationTypesWithCustomNames() : void
    {
        $schema = BuildSchema::build('
          schema {
            query: SomeQuery
            mutation: SomeMutation
            subscription: SomeSubscription
          }
          type SomeQuery { str: String }
          type SomeMutation { str: String }
          type SomeSubscription { str: String }
        ');

        $this->assertEquals('SomeQuery', $schema->getQueryType()->name);
        $this->assertEquals('SomeMutation', $schema->getMutationType()->name);
        $this->assertEquals('SomeSubscription', $schema->getSubscriptionType()->name);
    }

    /**
     * @see it('Default root operation type names')
     */
    public function testDefaultRootOperationTypeNames() : void
    {
        $schema = BuildSchema::build('
          type Query { str: String }
          type Mutation { str: String }
          type Subscription { str: String }
        ');
        $this->assertEquals('Query', $schema->getQueryType()->name);
        $this->assertEquals('Mutation', $schema->getMutationType()->name);
        $this->assertEquals('Subscription', $schema->getSubscriptionType()->name);
    }

    /**
     * @see it('can build invalid schema')
     */
    public function testCanBuildInvalidSchema() : void
    {
        $schema = BuildSchema::build('
          # Invalid schema, because it is missing query root type
          type Mutation {
            str: String
          }
        ');
        $errors = $schema->validate();
        $this->assertGreaterThan(0, $errors);
    }

    // Describe: Failures

    /**
     * @see it('Allows only a single schema definition')
     */
    public function testAllowsOnlySingleSchemaDefinition() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Must provide only one schema definition.');
        $body = '
schema {
  query: Hello
}

schema {
  query: Hello
}

type Hello {
  bar: Bar
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Allows only a single query type')
     */
    public function testAllowsOnlySingleQueryType() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Must provide only one query type in schema.');
        $body = '
schema {
  query: Hello
  query: Yellow
}

type Hello {
  bar: Bar
}

type Yellow {
  isColor: Boolean
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Allows only a single mutation type')
     */
    public function testAllowsOnlySingleMutationType() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Must provide only one mutation type in schema.');
        $body = '
schema {
  query: Hello
  mutation: Hello
  mutation: Yellow
}

type Hello {
  bar: Bar
}

type Yellow {
  isColor: Boolean
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Allows only a single subscription type')
     */
    public function testAllowsOnlySingleSubscriptionType() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Must provide only one subscription type in schema.');
        $body = '
schema {
  query: Hello
  subscription: Hello
  subscription: Yellow
}

type Hello {
  bar: Bar
}

type Yellow {
  isColor: Boolean
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Unknown type referenced')
     */
    public function testUnknownTypeReferenced() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Type "Bar" not found in document.');
        $body = '
schema {
  query: Hello
}

type Hello {
  bar: Bar
}
';
        $doc = Parser::parse($body);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @see it('Unknown type in interface list')
     */
    public function testUnknownTypeInInterfaceList() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Type "Bar" not found in document.');
        $body = '
type Query implements Bar {
  field: String
}
';
        $doc = Parser::parse($body);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @see it('Unknown type in union list')
     */
    public function testUnknownTypeInUnionList() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Type "Bar" not found in document.');
        $body = '
union TestUnion = Bar
type Query { testUnion: TestUnion }
';
        $doc = Parser::parse($body);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @see it('Unknown query type')
     */
    public function testUnknownQueryType() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified query type "Wat" not found in document.');
        $body = '
schema {
  query: Wat
}

type Hello {
  str: String
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Unknown mutation type')
     */
    public function testUnknownMutationType() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified mutation type "Wat" not found in document.');
        $body = '
schema {
  query: Hello
  mutation: Wat
}

type Hello {
  str: String
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Unknown subscription type')
     */
    public function testUnknownSubscriptionType() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified subscription type "Awesome" not found in document.');
        $body = '
schema {
  query: Hello
  mutation: Wat
  subscription: Awesome
}

type Hello {
  str: String
}

type Wat {
  str: String
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Does not consider operation names')
     */
    public function testDoesNotConsiderOperationNames() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified query type "Foo" not found in document.');
        $body = '
schema {
  query: Foo
}

query Foo { field }
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Does not consider fragment names')
     */
    public function testDoesNotConsiderFragmentNames() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified query type "Foo" not found in document.');
        $body = '
schema {
  query: Foo
}

fragment Foo on Type { field }
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Forbids duplicate type definitions')
     */
    public function testForbidsDuplicateTypeDefinitions() : void
    {
        $body = '
schema {
  query: Repeated
}

type Repeated {
  id: Int
}

type Repeated {
  id: String
}
';
        $doc = Parser::parse($body);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Type "Repeated" was defined more than once.');
        BuildSchema::buildAST($doc);
    }

    public function testSupportsTypeConfigDecorator() : void
    {
        $body = '
schema {
  query: Query
}

type Query {
  str: String
  color: Color
  hello: Hello
}

enum Color {
  RED
  GREEN
  BLUE
}

interface Hello {
  world: String
}
';
        $doc = Parser::parse($body);

        $decorated = [];
        $calls = [];

        $typeConfigDecorator = function($defaultConfig, $node, $allNodesMap) use (&$decorated, &$calls) {
            $decorated[] = $defaultConfig['name'];
            $calls[] = [$defaultConfig, $node, $allNodesMap];
            return ['description' => 'My description of ' . $node->name->value] + $defaultConfig;
        };

        $schema = BuildSchema::buildAST($doc, $typeConfigDecorator);
        $schema->getTypeMap();
        $this->assertEquals(['Query', 'Color', 'Hello'], $decorated);

        list($defaultConfig, $node, $allNodesMap) = $calls[0];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $node);
        $this->assertEquals('Query', $defaultConfig['name']);
        $this->assertInstanceOf(\Closure::class, $defaultConfig['fields']);
        $this->assertInstanceOf(\Closure::class, $defaultConfig['interfaces']);
        $this->assertArrayHasKey('description', $defaultConfig);
        $this->assertCount(5, $defaultConfig);
        $this->assertEquals(array_keys($allNodesMap), ['Query', 'Color', 'Hello']);
        $this->assertEquals('My description of Query', $schema->getType('Query')->description);


        list($defaultConfig, $node, $allNodesMap) = $calls[1];
        $this->assertInstanceOf(EnumTypeDefinitionNode::class, $node);
        $this->assertEquals('Color', $defaultConfig['name']);
        $enumValue = [
            'description' => '',
            'deprecationReason' => ''
        ];
        $this->assertArraySubset([
            'RED' => $enumValue,
            'GREEN' => $enumValue,
            'BLUE' => $enumValue,
        ], $defaultConfig['values']);
        $this->assertCount(4, $defaultConfig); // 3 + astNode
        $this->assertEquals(array_keys($allNodesMap), ['Query', 'Color', 'Hello']);
        $this->assertEquals('My description of Color', $schema->getType('Color')->description);

        list($defaultConfig, $node, $allNodesMap) = $calls[2];
        $this->assertInstanceOf(InterfaceTypeDefinitionNode::class, $node);
        $this->assertEquals('Hello', $defaultConfig['name']);
        $this->assertInstanceOf(\Closure::class, $defaultConfig['fields']);
        $this->assertArrayHasKey('description', $defaultConfig);
        $this->assertCount(4, $defaultConfig);
        $this->assertEquals(array_keys($allNodesMap), ['Query', 'Color', 'Hello']);
        $this->assertEquals('My description of Hello', $schema->getType('Hello')->description);
    }

    public function testCreatesTypesLazily() : void
    {
        $body = '
schema {
  query: Query
}

type Query {
  str: String
  color: Color
  hello: Hello
}

enum Color {
  RED
  GREEN
  BLUE
}

interface Hello {
  world: String
}

type World implements Hello {
  world: String
}
';
        $doc = Parser::parse($body);
        $created = [];

        $typeConfigDecorator = function($config, $node) use (&$created) {
            $created[] = $node->name->value;
            return $config;
        };

        $schema = BuildSchema::buildAST($doc, $typeConfigDecorator);
        $this->assertEquals(['Query'], $created);

        $schema->getType('Color');
        $this->assertEquals(['Query', 'Color'], $created);

        $schema->getType('Hello');
        $this->assertEquals(['Query', 'Color', 'Hello'], $created);

        $types = $schema->getTypeMap();
        $this->assertEquals(['Query', 'Color', 'Hello', 'World'], $created);
        $this->assertArrayHasKey('Query', $types);
        $this->assertArrayHasKey('Color', $types);
        $this->assertArrayHasKey('Hello', $types);
        $this->assertArrayHasKey('World', $types);
    }

}
