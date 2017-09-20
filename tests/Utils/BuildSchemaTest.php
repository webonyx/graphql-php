<?php
namespace GraphQL\Tests\Utils;

use GraphQL\GraphQL;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;

use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumValueDefinition;

class BuildSchemaTest extends \PHPUnit_Framework_TestCase
{
    // Describe: Schema Builder

    private function cycleOutput($body)
    {
        $ast = Parser::parse($body);
        $schema = BuildSchema::buildAST($ast);
        return "\n" . SchemaPrinter::doPrint($schema);
    }

    /**
     * @it can use built schema for limited execution
     */
    public function testUseBuiltSchemaForLimitedExecution()
    {
        $schema = BuildSchema::buildAST(Parser::parse('
            schema { query: Query }
            type Query {
                str: String
            }
        '));
        
        $result = GraphQL::execute($schema, '{ str }', ['str' => 123]);
        $this->assertEquals($result['data'], ['str' => 123]);
    }

    /**
     * @it can build a schema directly from the source
     */
    public function testBuildSchemaDirectlyFromSource()
    {
        $schema = BuildSchema::build("
            schema { query: Query }
            type Query {
                add(x: Int, y: Int): Int
            }
        ");

        $result = GraphQL::execute(
            $schema,
            '{ add(x: 34, y: 55) }',
            [
                'add' => function ($root, $args) {
                    return $args['x'] + $args['y'];
                }
            ]
        );
        $this->assertEquals($result, ['data' => ['add' => 89]]);
    }

    /**
     * @it Simple Type
     */
    public function testSimpleType()
    {
        $body = '
schema {
  query: HelloScalars
}

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
     * @it With directives
     */
    public function testWithDirectives()
    {
        $body = '
schema {
  query: Hello
}

directive @foo(arg: Int) on FIELD

type Hello {
  str: String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @it Supports descriptions
     */
    public function testSupportsDescriptions()
    {
        $body = '
schema {
  query: Hello
}

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
type Hello {
  # And a field to boot
  str: String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($body, $output);
    }

    /**
     * @it Maintains @skip & @include
     */
    public function testMaintainsSkipAndInclude()
    {
        $body = '
schema {
  query: Hello
}

type Hello {
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
     * @it Overriding directives excludes specified
     */
    public function testOverridingDirectivesExcludesSpecified()
    {
        $body = '
schema {
  query: Hello
}

directive @skip on FIELD
directive @include on FIELD
directive @deprecated on FIELD_DEFINITION

type Hello {
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
     * @it Type modifiers
     */
    public function testTypeModifiers()
    {
        $body = '
schema {
  query: HelloScalars
}

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
     * @it Recursive type
     */
    public function testRecursiveType()
    {
        $body = '
schema {
  query: Recurse
}

type Recurse {
  str: String
  recurse: Recurse
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @it Two types circular
     */
    public function testTwoTypesCircular()
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
     * @it Single argument field
     */
    public function testSingleArgumentField()
    {
        $body = '
schema {
  query: Hello
}

type Hello {
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
     * @it Simple type with multiple arguments
     */
    public function testSimpleTypeWithMultipleArguments()
    {
        $body = '
schema {
  query: Hello
}

type Hello {
  str(int: Int, bool: Boolean): String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @it Simple type with interface
     */
    public function testSimpleTypeWithInterface()
    {
        $body = '
schema {
  query: Hello
}

type Hello implements WorldInterface {
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
     * @it Simple output enum
     */
    public function testSimpleOutputEnum()
    {
        $body = '
schema {
  query: OutputEnumRoot
}

enum Hello {
  WORLD
}

type OutputEnumRoot {
  hello: Hello
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @it Multiple value enum
     */
    public function testMultipleValueEnum()
    {
        $body = '
schema {
  query: OutputEnumRoot
}

enum Hello {
  WO
  RLD
}

type OutputEnumRoot {
  hello: Hello
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @it Simple Union
     */
    public function testSimpleUnion()
    {
        $body = '
schema {
  query: Root
}

union Hello = World

type Root {
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
     * @it Multiple Union
     */
    public function testMultipleUnion()
    {
        $body = '
schema {
  query: Root
}

union Hello = WorldOne | WorldTwo

type Root {
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
     * @it CustomScalar
     */
    public function testCustomScalar()
    {
        $body = '
schema {
  query: Root
}

scalar CustomScalar

type Root {
  customScalar: CustomScalar
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @it CustomScalar
     */
    public function testInputObject()
    {
        $body = '
schema {
  query: Root
}

input Input {
  int: Int
}

type Root {
  field(in: Input): String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @it Simple argument field with default
     */
    public function testSimpleArgumentFieldWithDefault()
    {
        $body = '
schema {
  query: Hello
}

type Hello {
  str(int: Int = 2): String
}
';
        $output = $this->cycleOutput($body);
        $this->assertEquals($output, $body);
    }

    /**
     * @it Simple type with mutation
     */
    public function testSimpleTypeWithMutation()
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
     * @it Simple type with subscription
     */
    public function testSimpleTypeWithSubscription()
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
     * @it Unreferenced type implementing referenced interface
     */
    public function testUnreferencedTypeImplementingReferencedInterface()
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
     * @it Unreferenced type implementing referenced union
     */
    public function testUnreferencedTypeImplementingReferencedUnion()
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
     * @it Supports @deprecated
     */
    public function testSupportsDeprecated()
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
     * @it Correctly assign AST nodes
     */
    public function testCorrectlyAssignASTNodes()
    {

        $schema = BuildSchema::build('
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

      directive @test(arg: Int) on FIELD
    ');
        /** @var ObjectType $query */
        $query = $schema->getType('Query');
        $testInput = $schema->getType('TestInput');
        $testEnum = $schema->getType('TestEnum');
        $testUnion = $schema->getType('TestUnion');
        $testInterface = $schema->getType('TestInterface');
        $testType = $schema->getType('TestType');
        $testDirective = $schema->getDirective('test');

        $restoredIDL = SchemaPrinter::doPrint(BuildSchema::build(
            Printer::doPrint($schema->getAstNode()) . "\n" .
            Printer::doPrint($query->astNode) . "\n" .
            Printer::doPrint($testInput->astNode) . "\n" .
            Printer::doPrint($testEnum->astNode) . "\n" .
            Printer::doPrint($testUnion->astNode) . "\n" .
            Printer::doPrint($testInterface->astNode) . "\n" .
            Printer::doPrint($testType->astNode) . "\n" .
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
        $this->assertEquals('arg: Int', Printer::doPrint($testDirective->args[0]->astNode));
    }

    // Describe: Failures

    /**
     * @it Requires a schema definition or Query type
     */
    public function testRequiresSchemaDefinitionOrQueryType()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide schema definition with query type or a type named Query.');
        $body = '
type Hello {
  bar: Bar
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Allows only a single schema definition
     */
    public function testAllowsOnlySingleSchemaDefinition()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide only one schema definition.');
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
     * @it Requires a query type
     */
    public function testRequiresQueryType()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide schema definition with query type or a type named Query.');
        $body = '
schema {
  mutation: Hello
}

type Hello {
  bar: Bar
}
';
        $doc = Parser::parse($body);
        BuildSchema::buildAST($doc);
    }

    /**
     * @it Allows only a single query type
     */
    public function testAllowsOnlySingleQueryType()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide only one query type in schema.');
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
     * @it Allows only a single mutation type
     */
    public function testAllowsOnlySingleMutationType()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide only one mutation type in schema.');
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
     * @it Allows only a single subscription type
     */
    public function testAllowsOnlySingleSubscriptionType()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Must provide only one subscription type in schema.');
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
     * @it Unknown type referenced
     */
    public function testUnknownTypeReferenced()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Type "Bar" not found in document.');
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
     * @it Unknown type in interface list
     */
    public function testUnknownTypeInInterfaceList()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Type "Bar" not found in document.');
        $body = '
schema {
  query: Hello
}

type Hello implements Bar { }
';
        $doc = Parser::parse($body);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @it Unknown type in union list
     */
    public function testUnknownTypeInUnionList()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Type "Bar" not found in document.');
        $body = '
schema {
  query: Hello
}

union TestUnion = Bar
type Hello { testUnion: TestUnion }
';
        $doc = Parser::parse($body);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @it Unknown query type
     */
    public function testUnknownQueryType()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Specified query type "Wat" not found in document.');
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
     * @it Unknown mutation type
     */
    public function testUnknownMutationType()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Specified mutation type "Wat" not found in document.');
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
     * @it Unknown subscription type
     */
    public function testUnknownSubscriptionType()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Specified subscription type "Awesome" not found in document.');
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
     * @it Does not consider operation names
     */
    public function testDoesNotConsiderOperationNames()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Specified query type "Foo" not found in document.');
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
     * @it Does not consider fragment names
     */
    public function testDoesNotConsiderFragmentNames()
    {
        $this->setExpectedException('GraphQL\Error\Error', 'Specified query type "Foo" not found in document.');
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
     * @it Forbids duplicate type definitions
     */
    public function testForbidsDuplicateTypeDefinitions()
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

        $this->setExpectedException('GraphQL\Error\Error', 'Type "Repeated" was defined more than once.');
        BuildSchema::buildAST($doc);
    }

    public function testSupportsTypeConfigDecorator()
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
        $this->assertInstanceOf(\Closure::class, $defaultConfig['resolveType']);
        $this->assertArrayHasKey('description', $defaultConfig);
        $this->assertCount(5, $defaultConfig);
        $this->assertEquals(array_keys($allNodesMap), ['Query', 'Color', 'Hello']);
        $this->assertEquals('My description of Hello', $schema->getType('Hello')->description);
    }

    public function testCreatesTypesLazily()
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
