<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use function array_keys;
use Closure;
use function count;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;

/**
 * @phpstan-import-type BuildSchemaOptions from BuildSchema
 */
class BuildSchemaTest extends TestCaseBase
{
    use ArraySubsetAsserts;

    // Describe: Schema Builder

    /**
     * @see it('can use built schema for limited execution')
     */
    public function testUseBuiltSchemaForLimitedExecution(): void
    {
        $schema = BuildSchema::buildAST(Parser::parse('
            type Query {
                str: String
            }
        '));

        $data = ['str' => 123];

        self::assertEquals(
            [
                'data' => $data,
            ],
            GraphQL::executeQuery($schema, '{ str }', $data)->toArray()
        );
    }

    /**
     * @see it('can build a schema directly from the source')
     */
    public function testBuildSchemaDirectlyFromSource(): void
    {
        $schema = BuildSchema::build('
            type Query {
                add(x: Int, y: Int): Int
            }
        ');

        $root = [
            'add' => static function ($rootValue, $args) {
                return $args['x'] + $args['y'];
            },
        ];

        $result = GraphQL::executeQuery(
            $schema,
            '{ add(x: 34, y: 55) }',
            $root
        );
        self::assertEquals(['data' => ['add' => 89]], $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
    }

    /**
     * @see it('Simple Type')
     */
    public function testSimpleType(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('include standard type only if it is used')
     */
    public function testIncludeStandardTypeOnlyIfItIsUsed(): void
    {
        $schema = BuildSchema::build('type Query');

        // String and Boolean are always included through introspection types
        $typeMap = $schema->getTypeMap();
        self::assertArrayNotHasKey('Int', $typeMap);
        self::assertArrayNotHasKey('Float', $typeMap);
        self::assertArrayNotHasKey('ID', $typeMap);

        self::markTestIncomplete('TODO we differ from graphql-js due to lazy loading, see https://github.com/webonyx/graphql-php/issues/964#issuecomment-945969162');
        self::assertNull($schema->getType('Int'));
        self::assertNull($schema->getType('Float'));
        self::assertNull($schema->getType('ID'));
    }

    /**
     * @phpstan-param BuildSchemaOptions $options
     */
    private function cycleOutput(string $body, array $options = []): string
    {
        $ast = Parser::parse($body);
        $schema = BuildSchema::buildAST($ast, null, $options);

        return "\n" . SchemaPrinter::doPrint($schema);
    }

    /**
     * @see it('With directives')
     */
    public function testWithDirectives(): void
    {
        $body = '
directive @foo(arg: Int) on FIELD

directive @repeatableFoo(arg: Int) repeatable on FIELD

type Query {
  str: String
}
';
        $output = $this->cycleOutput($body);
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Supports descriptions')
     */
    public function testSupportsDescriptions(): void
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
        self::assertEquals($body, $output);
    }

    /**
     * @see it('Maintains @skip & @include')
     */
    public function testMaintainsSkipAndInclude(): void
    {
        $body = '
type Query {
  str: String
}
';
        $schema = BuildSchema::buildAST(Parser::parse($body));
        self::assertEquals(count($schema->getDirectives()), 3);
        self::assertEquals($schema->getDirective('skip'), Directive::skipDirective());
        self::assertEquals($schema->getDirective('include'), Directive::includeDirective());
        self::assertEquals($schema->getDirective('deprecated'), Directive::deprecatedDirective());
    }

    /**
     * @see it('Overriding directives excludes specified')
     */
    public function testOverridingDirectivesExcludesSpecified(): void
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
        self::assertEquals(count($schema->getDirectives()), 3);
        self::assertNotEquals($schema->getDirective('skip'), Directive::skipDirective());
        self::assertNotEquals($schema->getDirective('include'), Directive::includeDirective());
        self::assertNotEquals($schema->getDirective('deprecated'), Directive::deprecatedDirective());
    }

    /**
     * @see it('Adding directives maintains @skip & @include')
     */
    public function testAddingDirectivesMaintainsSkipAndInclude(): void
    {
        $body = '
      directive @foo(arg: Int) on FIELD

      type Query {
        str: String
      }
    ';
        $schema = BuildSchema::buildAST(Parser::parse($body));
        self::assertCount(4, $schema->getDirectives());
        self::assertNotEquals(null, $schema->getDirective('skip'));
        self::assertNotEquals(null, $schema->getDirective('include'));
        self::assertNotEquals(null, $schema->getDirective('deprecated'));
    }

    /**
     * @see it('Type modifiers')
     */
    public function testTypeModifiers(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Recursive type')
     */
    public function testRecursiveType(): void
    {
        $body = '
type Query {
  str: String
  recurse: Query
}
';
        $output = $this->cycleOutput($body);
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Two types circular')
     */
    public function testTwoTypesCircular(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Single argument field')
     */
    public function testSingleArgumentField(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Simple type with multiple arguments')
     */
    public function testSimpleTypeWithMultipleArguments(): void
    {
        $body = '
type Query {
  str(int: Int, bool: Boolean): String
}
';
        $output = $this->cycleOutput($body);
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Simple type with interface')
     */
    public function testSimpleTypeWithInterface(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Simple interface hierarchy')
     */
    public function testSimpleInterfaceHierarchy(): void
    {
        $body = '
interface Child implements Parent {
  str: String
}

type Hello implements Parent & Child {
  str: String
}

interface Parent {
  str: String
}
';
        $output = $this->cycleOutput($body);
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Simple output enum')
     */
    public function testSimpleOutputEnum(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Simple input enum')
     */
    public function testSimpleInputEnum(): void
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
        self::assertEquals($body, $output);
    }

    /**
     * @see it('Multiple value enum')
     */
    public function testMultipleValueEnum(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Simple Union')
     */
    public function testSimpleUnion(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Multiple Union')
     */
    public function testMultipleUnion(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Can build recursive Union')
     */
    public function testCanBuildRecursiveUnion(): void
    {
        $schema = BuildSchema::build('
          union Hello = Hello
    
          type Query {
            hello: Hello
          }
        ');
        $errors = $schema->validate();
        self::assertNotEmpty($errors);
    }

    /**
     * @see it('Custom Scalar')
     */
    public function testCustomScalar(): void
    {
        $body = '
scalar CustomScalar

type Query {
  customScalar: CustomScalar
}
';
        $output = $this->cycleOutput($body);
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Input Object')
     */
    public function testInputObject(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Simple argument field with default')
     */
    public function testSimpleArgumentFieldWithDefault(): void
    {
        $body = '
type Query {
  str(int: Int = 2): String
}
';
        $output = $this->cycleOutput($body);
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Custom scalar argument field with default')
     */
    public function testCustomScalarArgumentFieldWithDefault(): void
    {
        $body = '
scalar CustomScalar

type Query {
  str(int: CustomScalar = 2): String
}
';
        $output = $this->cycleOutput($body);
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Simple type with mutation')
     */
    public function testSimpleTypeWithMutation(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Simple type with subscription')
     */
    public function testSimpleTypeWithSubscription(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Unreferenced type implementing referenced interface')
     */
    public function testUnreferencedTypeImplementingReferencedInterface(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Unreferenced interface implementing referenced interface')
     */
    public function testUnreferencedInterfaceImplementingReferencedInterface(): void
    {
        $body = '
interface Child implements Parent {
  key: String
}

interface Parent {
  key: String
}

type Query {
  iface: Parent
}
';
        $output = $this->cycleOutput($body);
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Unreferenced type implementing referenced union')
     */
    public function testUnreferencedTypeImplementingReferencedUnion(): void
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
        self::assertEquals($output, $body);
    }

    /**
     * @see it('Supports @deprecated')
     */
    public function testSupportsDeprecated(): void
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
        self::assertEquals($output, $body);

        $ast = Parser::parse($body);
        $schema = BuildSchema::buildAST($ast);

        /** @var EnumType $myEnum */
        $myEnum = $schema->getType('MyEnum');

        $value = $myEnum->getValue('VALUE');
        self::assertInstanceOf(EnumValueDefinition::class, $value);
        self::assertFalse($value->isDeprecated());

        $oldValue = $myEnum->getValue('OLD_VALUE');
        self::assertInstanceOf(EnumValueDefinition::class, $oldValue);
        self::assertTrue($oldValue->isDeprecated());
        self::assertEquals('No longer supported', $oldValue->deprecationReason);

        $otherValue = $myEnum->getValue('OTHER_VALUE');
        self::assertInstanceOf(EnumValueDefinition::class, $otherValue);
        self::assertTrue($otherValue->isDeprecated());
        self::assertEquals('Terrible reasons', $otherValue->deprecationReason);

        $queryType = $schema->getType('Query');
        self::assertInstanceOf(ObjectType::class, $queryType);

        $rootFields = $queryType->getFields();
        self::assertEquals($rootFields['field1']->isDeprecated(), true);
        self::assertEquals($rootFields['field1']->deprecationReason, 'No longer supported');

        self::assertEquals($rootFields['field2']->isDeprecated(), true);
        self::assertEquals($rootFields['field2']->deprecationReason, 'Because I said so');
    }

    /**
     * @see it('Correctly assign AST nodes')
     */
    public function testCorrectlyAssignASTNodes(): void
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

        $query = $schema->getType('Query');
        self::assertInstanceOf(ObjectType::class, $query);

        $testInput = $schema->getType('TestInput');
        self::assertInstanceOf(InputObjectType::class, $testInput);

        $testEnum = $schema->getType('TestEnum');
        self::assertInstanceOf(EnumType::class, $testEnum);

        $testUnion = $schema->getType('TestUnion');
        self::assertInstanceOf(UnionType::class, $testUnion);

        $testInterface = $schema->getType('TestInterface');
        self::assertInstanceOf(InterfaceType::class, $testInterface);

        $testType = $schema->getType('TestType');
        self::assertInstanceOf(ObjectType::class, $testType);

        $testScalar = $schema->getType('TestScalar');
        self::assertInstanceOf(ScalarType::class, $testScalar);

        $testDirective = $schema->getDirective('test');
        self::assertInstanceOf(Directive::class, $testDirective);

        $schemaAst = $schema->getAstNode();
        self::assertInstanceOf(SchemaDefinitionNode::class, $schemaAst);

        $queryAst = $query->astNode;
        self::assertInstanceOf(ObjectTypeDefinitionNode::class, $queryAst);

        $testInputAst = $testInput->astNode;
        self::assertInstanceOf(InputObjectTypeDefinitionNode::class, $testInputAst);

        $testEnumAst = $testEnum->astNode;
        self::assertInstanceOf(EnumTypeDefinitionNode::class, $testEnumAst);

        $testUnionAst = $testUnion->astNode;
        self::assertInstanceOf(UnionTypeDefinitionNode::class, $testUnionAst);

        $testInterfaceAst = $testInterface->astNode;
        self::assertInstanceOf(InterfaceTypeDefinitionNode::class, $testInterfaceAst);

        $testTypeAst = $testType->astNode;
        self::assertInstanceOf(ObjectTypeDefinitionNode::class, $testTypeAst);

        $testScalarAst = $testScalar->astNode;
        self::assertInstanceOf(ScalarTypeDefinitionNode::class, $testScalarAst);

        $testDirectiveAst = $testDirective->astNode;
        self::assertInstanceOf(DirectiveDefinitionNode::class, $testDirectiveAst);

        $inner = Printer::doPrint($schemaAst) . "\n"
            . Printer::doPrint($queryAst) . "\n"
            . Printer::doPrint($testInputAst) . "\n"
            . Printer::doPrint($testEnumAst) . "\n"
            . Printer::doPrint($testUnionAst) . "\n"
            . Printer::doPrint($testInterfaceAst) . "\n"
            . Printer::doPrint($testTypeAst) . "\n"
            . Printer::doPrint($testScalarAst) . "\n"
            . Printer::doPrint($testDirectiveAst);

        $restoredIDL = SchemaPrinter::doPrint(BuildSchema::build($inner));

        self::assertEquals($restoredIDL, SchemaPrinter::doPrint($schema));

        $testField = $query->getField('testField');
        self::assertASTMatches('testField(testArg: TestInput): TestUnion', $testField->astNode);
        self::assertASTMatches('testArg: TestInput', $testField->args[0]->astNode);
        self::assertASTMatches('testInputField: TestEnum', $testInput->getField('testInputField')->astNode);
        self::assertASTMatches('TEST_VALUE', $testEnum->getValue('TEST_VALUE')->astNode ?? null);
        self::assertASTMatches('interfaceField: String', $testInterface->getField('interfaceField')->astNode);
        self::assertASTMatches('interfaceField: String', $testType->getField('interfaceField')->astNode);
        self::assertASTMatches('arg: TestScalar', $testDirective->args[0]->astNode);
    }

    /**
     * @see it('Root operation types with custom names')
     */
    public function testRootOperationTypesWithCustomNames(): void
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

        $query = $schema->getQueryType();
        self::assertInstanceOf(ObjectType::class, $query);
        self::assertEquals('SomeQuery', $query->name);

        $mutation = $schema->getMutationType();
        self::assertInstanceOf(ObjectType::class, $mutation);
        self::assertEquals('SomeMutation', $mutation->name);

        $subscription = $schema->getSubscriptionType();
        self::assertInstanceOf(ObjectType::class, $subscription);
        self::assertEquals('SomeSubscription', $subscription->name);
    }

    /**
     * @see it('Default root operation type names')
     */
    public function testDefaultRootOperationTypeNames(): void
    {
        $schema = BuildSchema::build('
          type Query { str: String }
          type Mutation { str: String }
          type Subscription { str: String }
        ');

        $query = $schema->getQueryType();
        self::assertInstanceOf(ObjectType::class, $query);
        self::assertEquals('Query', $query->name);

        $mutation = $schema->getMutationType();
        self::assertInstanceOf(ObjectType::class, $mutation);
        self::assertEquals('Mutation', $mutation->name);

        $subscription = $schema->getSubscriptionType();
        self::assertInstanceOf(ObjectType::class, $subscription);
        self::assertEquals('Subscription', $subscription->name);
    }

    /**
     * @see it('can build invalid schema')
     */
    public function testCanBuildInvalidSchema(): void
    {
        $schema = BuildSchema::build('
          # Invalid schema, because it is missing query root type
          type Mutation {
            str: String
          }
        ');
        $errors = $schema->validate();
        self::assertGreaterThan(0, $errors);
    }

    /**
     * @see it('Rejects invalid SDL')
     */
    public function testRejectsInvalidSDL(): void
    {
        $doc = Parser::parse('
          type Query {
            foo: String @unknown
          }
        ');
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Unknown directive "unknown".');
        BuildSchema::build($doc);
    }

    /**
     * @see it('Allows to disable SDL validation')
     */
    public function testAllowsToDisableSDLValidation(): void
    {
        $body = '
          type Query {
            foo: String @unknown
          }
        ';

        $schema = BuildSchema::build($body, null, ['assumeValid' => true]);
        self::assertCount(1, $schema->validate());

        $schema = BuildSchema::build($body, null, ['assumeValidSDL' => true]);
        self::assertCount(1, $schema->validate());
    }

    /**
     * @see it('Throws on unknown types')
     */
    public function testThrowsOnUnknownTypes(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionObject(new Error('Unknown type: "UnknownType".'));
        BuildSchema::build('
      type Query {
        unknown: UnknownType
      }
', null, ['assumeValidSDL' => true])->assertValid();
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/997
     */
    public function testBuiltSchemaReturnsNullForNonexistentType(): void
    {
        $schema = BuildSchema::build('type KnownType');
        self::assertNull($schema->getType('UnknownType'));
    }

    public function testSupportsTypeConfigDecorator(): void
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

        $typeConfigDecorator = static function ($defaultConfig, $node, $allNodesMap) use (&$decorated, &$calls) {
            $decorated[] = $defaultConfig['name'];
            $calls[] = [$defaultConfig, $node, $allNodesMap];

            return ['description' => 'My description of ' . $node->name->value] + $defaultConfig;
        };

        $schema = BuildSchema::buildAST($doc, $typeConfigDecorator);
        $schema->getTypeMap();
        self::assertEquals(['Query', 'Color', 'Hello'], $decorated);

        [$defaultConfig, $node, $allNodesMap] = $calls[0];
        self::assertInstanceOf(ObjectTypeDefinitionNode::class, $node);
        self::assertEquals('Query', $defaultConfig['name']);
        self::assertInstanceOf(Closure::class, $defaultConfig['fields']);
        self::assertInstanceOf(Closure::class, $defaultConfig['interfaces']);
        self::assertArrayHasKey('description', $defaultConfig);
        self::assertCount(5, $defaultConfig);
        self::assertEquals(array_keys($allNodesMap), ['Query', 'Color', 'Hello']);

        $query = $schema->getType('Query');
        self::assertInstanceOf(ObjectType::class, $query);
        self::assertEquals('My description of Query', $query->description);

        [$defaultConfig, $node, $allNodesMap] = $calls[1];
        self::assertInstanceOf(EnumTypeDefinitionNode::class, $node);
        self::assertEquals('Color', $defaultConfig['name']);
        $enumValue = [
            'description' => '',
            'deprecationReason' => '',
        ];
        self::assertArraySubset(
            [
                'RED' => $enumValue,
                'GREEN' => $enumValue,
                'BLUE' => $enumValue,
            ],
            $defaultConfig['values']
        );
        self::assertCount(4, $defaultConfig); // 3 + astNode
        self::assertEquals(array_keys($allNodesMap), ['Query', 'Color', 'Hello']);

        $color = $schema->getType('Color');
        self::assertInstanceOf(EnumType::class, $color);
        self::assertEquals('My description of Color', $color->description);

        [$defaultConfig, $node, $allNodesMap] = $calls[2];
        self::assertInstanceOf(InterfaceTypeDefinitionNode::class, $node);
        self::assertEquals('Hello', $defaultConfig['name']);
        self::assertInstanceOf(Closure::class, $defaultConfig['fields']);
        self::assertArrayHasKey('description', $defaultConfig);
        self::assertArrayHasKey('interfaces', $defaultConfig);
        self::assertCount(5, $defaultConfig);
        self::assertEquals(array_keys($allNodesMap), ['Query', 'Color', 'Hello']);

        $hello = $schema->getType('Hello');
        self::assertInstanceOf(InterfaceType::class, $hello);
        self::assertEquals('My description of Hello', $hello->description);
    }

    public function testCreatesTypesLazily(): void
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

        $typeConfigDecorator = static function ($config, $node) use (&$created) {
            $created[] = $node->name->value;

            return $config;
        };

        $schema = BuildSchema::buildAST($doc, $typeConfigDecorator);
        self::assertEquals(['Query'], $created);

        $schema->getType('Color');
        self::assertEquals(['Query', 'Color'], $created);

        $schema->getType('Hello');
        self::assertEquals(['Query', 'Color', 'Hello'], $created);

        $types = $schema->getTypeMap();
        self::assertEquals(['Query', 'Color', 'Hello', 'World'], $created);
        self::assertArrayHasKey('Query', $types);
        self::assertArrayHasKey('Color', $types);
        self::assertArrayHasKey('Hello', $types);
        self::assertArrayHasKey('World', $types);
    }
}
