<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use function array_keys;
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
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;
use function preg_match;
use function preg_replace;
use function property_exists;
use function trim;

/**
 * @phpstan-import-type BuildSchemaOptions from BuildSchema
 */
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
     * in-memory GraphQLSchema, and then finally printing that object into the SDL.
     *
     * @phpstan-param BuildSchemaOptions $options
     */
    private function cycleSDL(string $sdl, array $options = []): string
    {
        $ast = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($ast, null, $options);

        return "\n" . SchemaPrinter::doPrint($schema);
    }

    private function printASTNode($obj): string
    {
        Utils::invariant($obj !== null && property_exists($obj, 'astNode') && $obj->astNode !== null);

        return Printer::doPrint($obj->astNode);
    }

    /**
     * @param ScalarType|ObjectType|InterfaceType|UnionType|EnumType|InputObjectType $obj
     */
    private function printAllASTNodes(NamedType $obj): string
    {
        Utils::invariant(property_exists($obj, 'astNode') && property_exists($obj, 'extensionASTNodes') && $obj->extensionASTNodes !== null);

        return Printer::doPrint(new DocumentNode([
            'definitions' => new NodeList([
                $obj->astNode,
                ...$obj->extensionASTNodes,
            ]),
        ]));
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
     * @see it('Ignores non-type system definitions')
     */
    public function testIgnoresNonTypeSystemDefinitions(): void
    {
        $sdl = '
            type Query {
              str: String
            }
            
            fragment SomeFragment on Query {
              str
            }
        ';
        // Should not throw
        BuildSchema::build($sdl);
        self::assertTrue(true);
    }

    /**
     * @see it('Match order of default types and directives')
     */
    public function testMatchOrderOfDefaultTypesAndDirectives(): void
    {
        $schema = new Schema([]);
        $sdlSchema = BuildSchema::buildAST(
            new DocumentNode(['definitions' => new NodeList([])])
        );

        self::assertEquals($schema->getDirectives(), $sdlSchema->getDirectives());
        self::assertEquals($schema->getTypeMap(), $sdlSchema->getTypeMap());
    }

    /**
     * @see it('Empty Type')
     */
    public function testEmptyType(): void
    {
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
     * @see it('With directives')
     */
    public function testWithDirectives(): void
    {
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
            """Do you agree that this is the most creative schema ever?"""
            schema {
              query: Query
            }

            """This is a directive"""
            directive @foo(
              """It has an argument"""
              arg: Int
            ) on FIELD
            
            """Who knows what inside this scalar?"""
            scalar MysteryScalar
            
            """This is a input object type"""
            input FooInput {
              """It has a field"""
              field: Int
            }
            
            """This is a interface type"""
            interface Energy {
              """It also has a field"""
              str: String
            }
            
            """There is nothing inside!"""
            union BlackHole

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
     * @see it('Maintains @skip, @skip & @specifiedBy')
     */
    public function testMaintainsSkipAndInclude(): void
    {
        $schema = BuildSchema::buildAST(Parser::parse('type Query'));

        self::assertCount(4, $schema->getDirectives());
        self::assertEquals(Directive::skipDirective(), $schema->getDirective('skip'));
        self::assertEquals(Directive::includeDirective(), $schema->getDirective('include'));
        self::assertEquals(Directive::deprecatedDirective(), $schema->getDirective('deprecated'));
        self::assertEquals(Directive::specifiedByDirective(), $schema->getDirective('specifiedBy'));
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
            directive @specifiedBy on FIELD_DEFINITION
        '));
        self::assertCount(4, $schema->getDirectives());
        self::assertNotEquals(Directive::skipDirective(), $schema->getDirective('skip'));
        self::assertNotEquals(Directive::includeDirective(), $schema->getDirective('include'));
        self::assertNotEquals(Directive::deprecatedDirective(), $schema->getDirective('deprecated'));
        self::assertNotEquals(Directive::specifiedByDirective(), $schema->getDirective('specifiedBy'));
    }

    /**
     * @see it('Adding directives maintains @skip, @skip & @specifiedBy')
     */
    public function testAddingDirectivesMaintainsSkipAndInclude(): void
    {
        $sdl = $this->dedent('
            directive @foo(arg: Int) on FIELD
        ');
        $schema = BuildSchema::buildAST(Parser::parse($sdl));
        self::assertCount(5, $schema->getDirectives());
        self::assertNotEquals(null, $schema->getDirective('skip'));
        self::assertNotEquals(null, $schema->getDirective('include'));
        self::assertNotEquals(null, $schema->getDirective('deprecated'));
        self::assertNotEquals(null, $schema->getDirective('specifiedBy'));
    }

    /**
     * @see it('Type modifiers')
     */
    public function testTypeModifiers(): void
    {
        $sdl = $this->dedent('
            type Query {
              nonNullStr: String!
              listOfStrings: [String]
              listOfNonNullStrings: [String!]
              nonNullListOfStrings: [String]!
              nonNullListOfNonNullStrings: [String!]!
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
            interface EmptyInterface
        ');

        /** @var InterfaceTypeDefinitionNode $definition */
        $definition = Parser::parse($sdl)->definitions[0];
        self::assertCount(0, $definition->interfaces, 'The interfaces property must be an empty list.');

        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);
    }

    /**
     * @see it('Simple type with interface')
     */
    public function testSimpleTypeWithInterface(): void
    {
        $sdl = $this->dedent('
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
        // `graphql-js` has `query: Child` but that's incorrect as `query` has to be Object type
        $sdl = $this->dedent('
            schema {
              query: Hello
            }
            
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
     * @see it('Custom Scalar')
     */
    public function testCustomScalar(): void
    {
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
            type Concrete implements Interface {
              key: String
            }
            
            interface Interface {
              key: String
            }
            
            type Query {
              interface: Interface
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
        $sdl = $this->dedent('
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
        $sdl = $this->dedent('
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

            input MyInput {
              oldInput: String @deprecated
              otherInput: String @deprecated(reason: "Use newInput")
              newInput: String
            }
            
            type Query {
              field1: String @deprecated
              field2: Int @deprecated(reason: "Because I said so")
              enum: MyEnum
              field3(oldArg: String @deprecated, arg: String): String
              field4(oldArg: String @deprecated(reason: "Why not?"), arg: String): String
              field5(arg: MyInput): String
            }
        ');

        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);

        $ast = Parser::parse($sdl);
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
        $queryType = $schema->getType('Query');
        $rootFields = $queryType->getFields();
        self::assertEquals(true, $rootFields['field1']->isDeprecated());
        self::assertEquals('No longer supported', $rootFields['field1']->deprecationReason);

        self::assertEquals(true, $rootFields['field2']->isDeprecated());
        self::assertEquals('Because I said so', $rootFields['field2']->deprecationReason);

        /** @var InputObjectType $type */
        $type = $schema->getType('MyInput');
        $inputFields = $type->getFields();
        self::assertEquals(null, $inputFields['newInput']->deprecationReason);
        self::assertEquals('No longer supported', $inputFields['oldInput']->deprecationReason);
        self::assertEquals('Use newInput', $inputFields['otherInput']->deprecationReason);

        self::assertEquals('No longer supported', $rootFields['field3']->args[0]->deprecationReason);
        self::assertEquals('Why not?', $rootFields['field4']->args[0]->deprecationReason);
    }

    /**
     * @see it('Supports @specifiedBy')
     */
    public function testSupportsSpecifiedBy(): void
    {
        $sdl = $this->dedent('
            scalar Foo @specifiedBy(url: "https://example.com/foo_spec")
            
            type Query {
              foo: Foo @deprecated
            }
        ');

        $output = $this->cycleSDL($sdl);
        self::assertEquals($sdl, $output);

        $schema = BuildSchema::build($sdl);

        self::assertEquals('https://example.com/foo_spec', $schema->getType('Foo')->specifiedByURL);
    }

    /**
     * @see it('Correctly extend scalar type')
     */
    public function testCorrectlyExtendScalarType(): void
    {
        $scalarSDL = $this->dedent('
            scalar SomeScalar
            
            extend scalar SomeScalar @foo
            
            extend scalar SomeScalar @bar
        ');

        $schema = BuildSchema::build("
            ${scalarSDL}
            directive @foo on SCALAR
            directive @bar on SCALAR
        ");

        /** @var ScalarType $someScalar */
        $someScalar = $schema->getType('SomeScalar');

        $expectedSomeScalarSDL = $this->dedent('
            scalar SomeScalar
        ');

        self::assertEquals($expectedSomeScalarSDL, SchemaPrinter::printType($someScalar) . "\n");
        self::assertEquals($scalarSDL, $this->printAllASTNodes($someScalar));
    }

    /**
     * @see it('Correctly extend object type')
     */
    public function testCorrectlyExtendObjectType(): void
    {
        $objectSDL = $this->dedent('
            type SomeObject implements Foo {
              first: String
            }
            
            extend type SomeObject implements Bar {
              second: Int
            }
            
            extend type SomeObject implements Baz {
              third: Float
            }
        ');

        $schema = BuildSchema::build("
            ${objectSDL}
            interface Foo
            interface Bar
            interface Baz
        ");

        /** @var ObjectType $someObject */
        $someObject = $schema->getType('SomeObject');

        $expectedSomeObjectSDL = $this->dedent('
            type SomeObject implements Foo & Bar & Baz {
              first: String
              second: Int
              third: Float
            }
        ');

        self::assertEquals($expectedSomeObjectSDL, SchemaPrinter::printType($someObject) . "\n");
        self::assertEquals($objectSDL, $this->printAllASTNodes($someObject));
    }

    /**
     * @see it('Correctly extend interface type')
     */
    public function testCorrectlyExtendInterfaceType(): void
    {
        $interfaceSDL = $this->dedent('
            interface SomeInterface {
              first: String
            }
            
            extend interface SomeInterface {
              second: Int
            }
            
            extend interface SomeInterface {
              third: Float
            }
        ');

        $schema = BuildSchema::build($interfaceSDL);

        /** @var InterfaceType $someInterface */
        $someInterface = $schema->getType('SomeInterface');

        $expectedSomeInterfaceSDL = $this->dedent('
            interface SomeInterface {
              first: String
              second: Int
              third: Float
            }
        ');

        self::assertEquals($expectedSomeInterfaceSDL, SchemaPrinter::printType($someInterface) . "\n");
        self::assertEquals($interfaceSDL, $this->printAllASTNodes($someInterface));
    }

    /**
     * @see it('Correctly extend union type')
     */
    public function testCorrectlyExtendUnionType(): void
    {
        $unionSDL = $this->dedent('
            union SomeUnion = FirstType
            
            extend union SomeUnion = SecondType
            
            extend union SomeUnion = ThirdType
        ');

        $schema = BuildSchema::build("
            ${unionSDL}
            type FirstType
            type SecondType
            type ThirdType
        ");

        /** @var UnionType $someUnion */
        $someUnion = $schema->getType('SomeUnion');

        $expectedSomeUnionSDL = $this->dedent('
            union SomeUnion = FirstType | SecondType | ThirdType
        ');

        self::assertEquals($expectedSomeUnionSDL, SchemaPrinter::printType($someUnion) . "\n");
        self::assertEquals($unionSDL, $this->printAllASTNodes($someUnion));
    }

    /**
     * @see it('Correctly extend enum type')
     */
    public function testCorrectlyExtendEnumType(): void
    {
        $enumSDL = $this->dedent('
            enum SomeEnum {
              FIRST
            }
            
            extend enum SomeEnum {
              SECOND
            }
            
            extend enum SomeEnum {
              THIRD
            }
        ');

        $schema = BuildSchema::build($enumSDL);

        /** @var EnumType $someEnum */
        $someEnum = $schema->getType('SomeEnum');

        $expectedSomeEnumSDL = $this->dedent('
            enum SomeEnum {
              FIRST
              SECOND
              THIRD
            }
        ');

        self::assertEquals($expectedSomeEnumSDL, SchemaPrinter::printType($someEnum) . "\n");
        self::assertEquals($enumSDL, $this->printAllASTNodes($someEnum));
    }

    /**
     * @see it('Correctly extend input object type')
     */
    public function testCorrectlyExtendInputObjectType(): void
    {
        $inputSDL = $this->dedent('
            input SomeInput {
              first: String
            }
            
            extend input SomeInput {
              second: Int
            }
            
            extend input SomeInput {
              third: Float
            }
        ');

        $schema = BuildSchema::build($inputSDL);

        /** @var InputObjectType $someInput */
        $someInput = $schema->getType('SomeInput');

        $expectedSomeInputSDL = $this->dedent('
            input SomeInput {
              first: String
              second: Int
              third: Float
            }
        ');

        self::assertEquals($expectedSomeInputSDL, SchemaPrinter::printType($someInput) . "\n");
        self::assertEquals($inputSDL, $this->printAllASTNodes($someInput));
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
        $testScalar = $schema->getType('TestScalar');
        $testDirective = $schema->getDirective('test');

        $schemaASTDefinitions = new NodeList([
            $schema->getAstNode(),
            $query->astNode,
            $testInput->astNode,
            $testEnum->astNode,
            $testUnion->astNode,
            $testInterface->astNode,
            $testType->astNode,
            $testScalar->astNode,
            $testDirective->astNode,
        ]);

        self::assertEquals($schemaASTDefinitions, $ast->definitions);

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
     * @see it('Do not override standard types')
     */
    public function testDoNotOverrideStandardTypes(): void
    {
        // NOTE: not sure it's desired behaviour to just silently ignore override
        // attempts so just documenting it here.

        $schema = BuildSchema::build('
            scalar ID
            
            scalar __Schema
        ');

        self::assertSame(Type::id(), $schema->getType('ID'));
        self::assertSame(Introspection::_schema(), $schema->getType('__Schema'));
    }

    /**
     * @see it('Allows to reference introspection types')
     */
    public function testAllowsToReferenceIntrospectionTypes(): void
    {
        $schema = BuildSchema::build('
            type Query {
              introspectionField: __EnumValue
            }
        ');

        $queryType = $schema->getQueryType();
        self::assertEquals('__EnumValue', $queryType->getField('introspectionField')->getType()->name);
        self::assertSame(Introspection::_enumValue(), $schema->getType('__EnumValue'));
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
        $this->expectExceptionMessage('Unknown directive "@unknown".');
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

        $schema = BuildSchema::build($sdl, null, ['assumeValid' => true]);
        self::assertCount(1, $schema->validate());

        $schema = BuildSchema::build($sdl, null, ['assumeValidSDL' => true]);
        self::assertCount(1, $schema->validate());
    }

    /**
     * @see it('Throws on unknown types')
     */
    public function testThrowsOnUnknownTypes(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionObject(new Error('Unknown type: "UnknownType".'));
        $sdl = '
            type Query {
              unknown: UnknownType
            }
        ';
        BuildSchema::build($sdl, null, ['assumeValidSDL' => true])->assertValid();
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
            
            type World implements Hello {
              world: String
            }
        ';
        $doc = Parser::parse($sdl);
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
