<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use Closure;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function preg_match;
use function preg_replace;
use function property_exists;
use function trim;

class BuildSchemaTest extends TestCase
{
    use ArraySubsetAsserts;

    protected function dedent(string $str): string
    {
        $trimmedStr = trim($str, "\n");
        $trimmedStr = preg_replace('/[ \t]*$/', '', $trimmedStr);

        preg_match('/^[ \t]*/', $trimmedStr, $indentMatch);
        $indent = $indentMatch[0];

        return preg_replace('/^' . $indent . '/m', '', $trimmedStr);
    }

    /**
     * This function does a full cycle of going from a string with the contents of
     * the SDL, parsed in a schema AST, materializing that schema AST into an
     * in-memory GraphQLSchema, and then finally printing that object into the SDL
     */
    private function cycleSDL($sdl, $options = []): string
    {
        $ast    = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($ast, null, $options);

        return SchemaPrinter::doPrint($schema, $options);
    }

    private function printASTNode($obj): string
    {
        Utils::invariant($obj !== null && property_exists($obj, 'astNode') && $obj->astNode !== null);

        return Printer::doPrint($obj->astNode);
    }

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

        $result = GraphQL::executeQuery($schema, '{ str }', ['str' => 123]);
        self::assertEquals(['str' => 123], $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)['data']);
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
     * @see it('Empty Type')
     */
    public function testEmptyType(): void
    {
        $sdl    = $this->dedent('
            type EmptyType
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple Type')
     */
    public function testSimpleType(): void
    {
        $sdl    = $this->dedent('
            type Query {
              str: String
              int: Int
              float: Float
              id: ID
              bool: Boolean
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);

        $schema = BuildSchema::build($sdl);
        // Built-ins are used
        self::assertSame(Type::int(), $schema->getType('Int'));
        self::assertSame(Type::float(), $schema->getType('Float'));
        self::assertSame(Type::string(), $schema->getType('String'));
        self::assertSame(Type::boolean(), $schema->getType('Boolean'));
        self::assertSame(Type::id(), $schema->getType('ID'));
    }

    /**
     * @see it('include standard type only if it is used')
     */
    public function testIncludeStandardTypeOnlyIfItIsUsed(): void
    {
        $schema = BuildSchema::build('type Query');
        // String and Boolean are always included through introspection types
        self::assertNull($schema->getType('Int'));
        self::assertNull($schema->getType('Float'));
        self::assertNull($schema->getType('ID'));
    }

    /**
     * @see it('With directives')
     */
    public function testWithDirectives(): void
    {
        $sdl    = $this->dedent('
            directive @foo(arg: Int) on FIELD
            
            directive @repeatableFoo(arg: Int) repeatable on FIELD
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Supports descriptions')
     */
    public function testSupportsDescriptions(): void
    {
        $sdl    = $this->dedent('
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
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Supports option for comment descriptions')
     */
    public function testSupportsOptionForCommentDescriptions(): void
    {
        $sdl    = $this->dedent('
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
        ');
        $output = $this->cycleSDL($sdl, ['commentDescriptions' => true]);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Maintains @skip & @include')
     */
    public function testMaintainsSkipAndInclude(): void
    {
        $schema = BuildSchema::buildAST(Parser::parse('type Query'));

        self::assertCount(3, $schema->getDirectives());
        self::assertEquals(Directive::skipDirective(), $schema->getDirective('skip'));
        self::assertEquals(Directive::includeDirective(), $schema->getDirective('include'));
        self::assertEquals(Directive::deprecatedDirective(), $schema->getDirective('deprecated'));
    }

    /**
     * @see it('Overriding directives excludes specified')
     */
    public function testOverridingDirectivesExcludesSpecified(): void
    {
        $schema = BuildSchema::buildAST(Parser::parse('
            directive @skip on FIELD
            directive @include on FIELD
            directive @deprecated on FIELD_DEFINITION
        '));
        self::assertCount(3, $schema->getDirectives());
        self::assertNotEquals(Directive::skipDirective(), $schema->getDirective('skip'));
        self::assertNotEquals(Directive::includeDirective(), $schema->getDirective('include'));
        self::assertNotEquals(Directive::deprecatedDirective(), $schema->getDirective('deprecated'));
    }

    /**
     * @see it('Adding directives maintains @skip & @include')
     */
    public function testAddingDirectivesMaintainsSkipAndInclude(): void
    {
        $sdl    = $this->dedent('
            directive @foo(arg: Int) on FIELD
        ');
        $schema = BuildSchema::buildAST(Parser::parse($sdl));
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
        $sdl    = $this->dedent('
            type Query {
              nonNullStr: String!
              listOfStrs: [String]
              listOfNonNullStrs: [String!]
              nonNullListOfStrs: [String]!
              nonNullListOfNonNullStrs: [String!]!
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Recursive type')
     */
    public function testRecursiveType(): void
    {
        $sdl    = $this->dedent('
            type Query {
              str: String
              recurse: Query
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Two types circular')
     */
    public function testTwoTypesCircular(): void
    {
        $sdl    = $this->dedent('
            type TypeOne {
              str: String
              typeTwo: TypeTwo
            }
            
            type TypeTwo {
              str: String
              typeOne: TypeOne
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Single argument field')
     */
    public function testSingleArgumentField(): void
    {
        $sdl    = $this->dedent('
            type Query {
              str(int: Int): String
              floatToStr(float: Float): String
              idToStr(id: ID): String
              booleanToStr(bool: Boolean): String
              strToStr(bool: String): String
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple type with multiple arguments')
     */
    public function testSimpleTypeWithMultipleArguments(): void
    {
        $sdl    = $this->dedent('
            type Query {
              str(int: Int, bool: Boolean): String
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Empty interface')
     */
    public function testEmptyInterface(): void
    {
        $sdl    = $this->dedent('
            interface EmptyInterface
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple type with interface')
     */
    public function testSimpleTypeWithInterface(): void
    {
        $sdl    = $this->dedent('
            type Query implements WorldInterface {
              str: String
            }
            
            interface WorldInterface {
              str: String
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple interface hierarchy')
     */
    public function testSimpleInterfaceHierarchy(): void
    {
        $sdl    = $this->dedent('
            interface Child implements Parent {
              str: String
            }
            
            type Hello implements Parent & Child {
              str: String
            }
            
            interface Parent {
              str: String
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Empty enum')
     */
    public function testEmptyEnum(): void
    {
        $sdl    = $this->dedent('
            enum EmptyEnum
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple output enum')
     */
    public function testSimpleOutputEnum(): void
    {
        $sdl    = $this->dedent('
            enum Hello {
              WORLD
            }
            
            type Query {
              hello: Hello
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple input enum')
     */
    public function testSimpleInputEnum(): void
    {
        $sdl    = $this->dedent('
            enum Hello {
              WORLD
            }
            
            type Query {
              str(hello: Hello): String
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Multiple value enum')
     */
    public function testMultipleValueEnum(): void
    {
        $sdl    = $this->dedent('
            enum Hello {
              WO
              RLD
            }
            
            type Query {
              hello: Hello
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Empty union')
     */
    public function testEmptyUnion(): void
    {
        $sdl    = $this->dedent('
            union EmptyUnion
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple Union')
     */
    public function testSimpleUnion(): void
    {
        $sdl    = $this->dedent('
            union Hello = World
            
            type Query {
              hello: Hello
            }
            
            type World {
              str: String
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Multiple Union')
     */
    public function testMultipleUnion(): void
    {
        $sdl    = $this->dedent('
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
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
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
     * @see it('Specifying Union type using __typename')
     */
    public function testSpecifyingUnionTypeUsingTypename(): void
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

        $rootValue = [
            'fruits' => [
                [
                    'color' => 'green',
                    '__typename' => 'Apple',
                ],
                [
                    'length' => 5,
                    '__typename' => 'Banana',
                ],
            ],
        ];

        $expected = [
            'data' => [
                'fruits' => [
                    ['color' => 'green'],
                    ['length' => 5],
                ],
            ],
        ];

        $result = GraphQL::executeQuery($schema, $query, $rootValue);
        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
    }

    /**
     * @see it('Specifying Interface type using __typename')
     */
    public function testSpecifyingInterfaceUsingTypename(): void
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

        $rootValue = [
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
                ],
            ],
        ];

        $expected = [
            'data' => [
                'characters' => [
                    ['name' => 'Han Solo', 'totalCredits' => 10],
                    ['name' => 'R2-D2', 'primaryFunction' => 'Astromech'],
                ],
            ],
        ];

        $result = GraphQL::executeQuery($schema, $query, $rootValue);
        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
    }

    /**
     * @see it('Custom Scalar')
     */
    public function testCustomScalar(): void
    {
        $sdl    = $this->dedent('
            scalar CustomScalar
            
            type Query {
              customScalar: CustomScalar
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Empty Input Object')
     */
    public function testEmptyInputObject(): void
    {
        $sdl    = $this->dedent('
            input EmptyInputObject
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Input Object')
     */
    public function testInputObject(): void
    {
        $sdl    = $this->dedent('
            input Input {
              int: Int
            }
            
            type Query {
              field(in: Input): String
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple argument field with default')
     */
    public function testSimpleArgumentFieldWithDefault(): void
    {
        $sdl    = $this->dedent('
            type Query {
              str(int: Int = 2): String
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Custom scalar argument field with default')
     */
    public function testCustomScalarArgumentFieldWithDefault(): void
    {
        $sdl    = $this->dedent('
            scalar CustomScalar
            
            type Query {
              str(int: CustomScalar = 2): String
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple type with mutation')
     */
    public function testSimpleTypeWithMutation(): void
    {
        $sdl    = $this->dedent('
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
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple type with subscription')
     */
    public function testSimpleTypeWithSubscription(): void
    {
        $sdl    = $this->dedent('
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
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Unreferenced type implementing referenced interface')
     */
    public function testUnreferencedTypeImplementingReferencedInterface(): void
    {
        $sdl    = $this->dedent('
            type Concrete implements Iface {
              key: String
            }
            
            interface Iface {
              key: String
            }
            
            type Query {
              iface: Iface
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Unreferenced interface implementing referenced interface')
     */
    public function testUnreferencedInterfaceImplementingReferencedInterface(): void
    {
        $sdl    = $this->dedent('
            interface Child implements Parent {
              key: String
            }
            
            interface Parent {
              key: String
            }
            
            type Query {
              iface: Parent
            }
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Unreferenced type implementing referenced union')
     */
    public function testUnreferencedTypeImplementingReferencedUnion(): void
    {
        $sdl    = $this->dedent('
            type Concrete {
              key: String
            }
            
            type Query {
              union: Union
            }
            
            union Union = Concrete
        ');
        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Supports @deprecated')
     */
    public function testSupportsDeprecated(): void
    {
        $sdl = $this->dedent('
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
        ');

        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);

        $ast    = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($ast);

        /** @var EnumType $myEnum */
        $myEnum = $schema->getType('MyEnum');

        $value = $myEnum->getValue('VALUE');
        self::assertFalse($value->isDeprecated());

        $oldValue = $myEnum->getValue('OLD_VALUE');
        self::assertTrue($oldValue->isDeprecated());
        self::assertEquals('No longer supported', $oldValue->deprecationReason);

        $otherValue = $myEnum->getValue('OTHER_VALUE');
        self::assertTrue($otherValue->isDeprecated());
        self::assertEquals('Terrible reasons', $otherValue->deprecationReason);

        /** @var ObjectType $queryType */
        $queryType  = $schema->getType('Query');
        $rootFields = $queryType->getFields();
        self::assertEquals(true, $rootFields['field1']->isDeprecated());
        self::assertEquals('No longer supported', $rootFields['field1']->deprecationReason);

        self::assertEquals(true, $rootFields['field2']->isDeprecated());
        self::assertEquals('Because I said so', $rootFields['field2']->deprecationReason);
    }

    /**
     * @see it('Correctly assign AST nodes')
     */
    public function testCorrectlyAssignASTNodes(): void
    {
        $sdl = '
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
        ';

        $ast = Parser::parse($sdl, ['noLocation' => true]);

        $schema = BuildSchema::buildAST($ast);
        /** @var ObjectType $query */
        $query = $schema->getType('Query');
        /** @var InputObjectType $testInput */
        $testInput = $schema->getType('TestInput');
        /** @var EnumType $testEnum */
        $testEnum = $schema->getType('TestEnum');
        /** @var UnionType $testUnion */
        $testUnion = $schema->getType('TestUnion');
        /** @var InterfaceType $testInterface */
        $testInterface = $schema->getType('TestInterface');
        /** @var ObjectType $testType */
        $testType = $schema->getType('TestType');
        /** @var ScalarType $testScalar */
        $testScalar    = $schema->getType('TestScalar');
        $testDirective = $schema->getDirective('test');

        $restoredSchemaAST = new DocumentNode([
            'definitions' => new NodeList([
                $schema->getAstNode(),
                $query->astNode,
                $testInput->astNode,
                $testEnum->astNode,
                $testUnion->astNode,
                $testInterface->astNode,
                $testType->astNode,
                $testScalar->astNode,
                $testDirective->astNode,
            ]),
            'loc' => null,
        ]);

        self::assertEquals($restoredSchemaAST, $ast);

        $testField = $query->getField('testField');
        self::assertEquals('testField(testArg: TestInput): TestUnion', $this->printASTNode($testField));
        self::assertEquals('testArg: TestInput', $this->printASTNode($testField->args[0]));
        self::assertEquals(
            'testInputField: TestEnum',
            $this->printASTNode($testInput->getField('testInputField'))
        );
        self::assertEquals('TEST_VALUE', $this->printASTNode($testEnum->getValue('TEST_VALUE')));
        self::assertEquals(
            'interfaceField: String',
            $this->printASTNode($testInterface->getField('interfaceField'))
        );
        self::assertEquals('interfaceField: String', $this->printASTNode($testType->getField('interfaceField')));
        self::assertEquals('arg: TestScalar', $this->printASTNode($testDirective->args[0]));
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
            type SomeQuery
            type SomeMutation
            type SomeSubscription
        ');

        self::assertEquals('SomeQuery', $schema->getQueryType()->name);
        self::assertEquals('SomeMutation', $schema->getMutationType()->name);
        self::assertEquals('SomeSubscription', $schema->getSubscriptionType()->name);
    }

    /**
     * @see it('Default root operation type names')
     */
    public function testDefaultRootOperationTypeNames(): void
    {
        $schema = BuildSchema::build('
            type Query
            type Mutation
            type Subscription
        ');

        self::assertEquals('Query', $schema->getQueryType()->name);
        self::assertEquals('Mutation', $schema->getMutationType()->name);
        self::assertEquals('Subscription', $schema->getSubscriptionType()->name);
    }

    /**
     * @see it('can build invalid schema')
     */
    public function testCanBuildInvalidSchema(): void
    {
        // Invalid schema, because it is missing query root type
        $schema = BuildSchema::build('type Mutation');
        $errors = $schema->validate();
        self::assertNotEmpty($errors);
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
        $sdl = '
            type Query {
              foo: String @unknown
            }
        ';
        // Should not throw:
        BuildSchema::build($sdl, null, ['assumeValid' => true]);
        BuildSchema::build($sdl, null, ['assumeValidSDL' => true]);
        self::assertTrue(true);
    }

    public function testSupportsTypeConfigDecorator(): void
    {
        $sdl = '
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
        $doc = Parser::parse($sdl);

        $decorated = [];
        $calls     = [];

        $typeConfigDecorator = static function ($defaultConfig, $node, $allNodesMap) use (&$decorated, &$calls) {
            $decorated[] = $defaultConfig['name'];
            $calls[]     = [$defaultConfig, $node, $allNodesMap];

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
        self::assertEquals(['Query', 'Color', 'Hello'], array_keys($allNodesMap));
        self::assertEquals('My description of Query', $schema->getType('Query')->description);

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
        self::assertEquals(['Query', 'Color', 'Hello'], array_keys($allNodesMap));
        self::assertEquals('My description of Color', $schema->getType('Color')->description);

        [$defaultConfig, $node, $allNodesMap] = $calls[2];
        self::assertInstanceOf(InterfaceTypeDefinitionNode::class, $node);
        self::assertEquals('Hello', $defaultConfig['name']);
        self::assertInstanceOf(Closure::class, $defaultConfig['fields']);
        self::assertArrayHasKey('description', $defaultConfig);
        self::assertArrayHasKey('interfaces', $defaultConfig);
        self::assertCount(5, $defaultConfig);
        self::assertEquals(['Query', 'Color', 'Hello'], array_keys($allNodesMap));
        self::assertEquals('My description of Hello', $schema->getType('Hello')->description);
    }

    public function testCreatesTypesLazily(): void
    {
        $sdl     = '
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
        $doc     = Parser::parse($sdl);
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
