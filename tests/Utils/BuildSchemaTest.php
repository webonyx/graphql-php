<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SerializationError;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Validator\Rules\KnownDirectives;

/** @phpstan-import-type UnnamedFieldDefinitionConfig from FieldDefinition */
final class BuildSchemaTest extends TestCaseBase
{
    use ArraySubsetAsserts;

    /**
     * This function does a full cycle of going from a string with the contents of
     * the SDL, parsed in a schema AST, materializing that schema AST into an
     * in-memory GraphQLSchema, and then finally printing that object into the SDL.
     *
     * @throws \Exception
     * @throws \JsonException
     * @throws Error
     * @throws InvariantViolation
     * @throws SerializationError
     */
    private static function assertCycle(string $sdl): void
    {
        $ast = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($ast);
        $cycled = SchemaPrinter::doPrint($schema);

        self::assertSame($sdl, $cycled);
    }

    /** @param ScalarType|ObjectType|InterfaceType|UnionType|EnumType|InputObjectType $obj */
    private function printAllASTNodes(NamedType $obj): string
    {
        self::assertNotNull($obj->astNode);

        return Printer::doPrint(new DocumentNode([
            'definitions' => new NodeList([
                $obj->astNode,
                ...$obj->extensionASTNodes,
            ]),
        ]));
    }

    // Describe: Schema Builder

    /** @see it('can use built schema for limited execution') */
    public function testUseBuiltSchemaForLimitedExecution(): void
    {
        $schema = BuildSchema::buildAST(Parser::parse('
            type Query {
                str: String
            }
        '));

        $data = ['str' => '123'];

        self::assertSame(
            [
                'data' => $data,
            ],
            GraphQL::executeQuery($schema, '{ str }', $data)->toArray()
        );
    }

    /** @see it('can build a schema directly from the source') */
    public function testBuildSchemaDirectlyFromSource(): void
    {
        $schema = BuildSchema::build('
            type Query {
                add(x: Int, y: Int): Int
            }
        ');

        $root = [
            'add' => static fn ($rootValue, array $args): int => $args['x'] + $args['y'],
        ];

        $result = GraphQL::executeQuery(
            $schema,
            '{ add(x: 34, y: 55) }',
            $root
        );
        self::assertSame(['data' => ['add' => 89]], $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
    }

    /** @see it('Ignores non-type system definitions') */
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
        $this->assertDidNotCrash();
    }

    /** @see it('Match order of default types and directives') */
    public function testMatchOrderOfDefaultTypesAndDirectives(): void
    {
        $schema = new Schema([]);
        $sdlSchema = BuildSchema::buildAST(
            new DocumentNode(['definitions' => new NodeList([])])
        );

        self::assertEquals(array_values($schema->getDirectives()), $sdlSchema->getDirectives());
        self::assertSame($schema->getTypeMap(), $sdlSchema->getTypeMap());
    }

    /** @see it('Empty Type') */
    public function testEmptyType(): void
    {
        $sdl = <<<GRAPHQL
            type EmptyType

            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Simple Type') */
    public function testSimpleType(): void
    {
        $sdl = <<<GRAPHQL
            type Query {
              str: String
              int: Int
              float: Float
              id: ID
              bool: Boolean
            }
            
            GRAPHQL;
        self::assertCycle($sdl);

        $schema = BuildSchema::build($sdl);
        // Built-ins are used
        self::assertSame(Type::int(), $schema->getType('Int'));
        self::assertSame(Type::float(), $schema->getType('Float'));
        self::assertSame(Type::string(), $schema->getType('String'));
        self::assertSame(Type::boolean(), $schema->getType('Boolean'));
        self::assertSame(Type::id(), $schema->getType('ID'));
    }

    /** @see it('include standard type only if it is used') */
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

    /** @see it('With directives') */
    public function testWithDirectives(): void
    {
        $sdl = <<<GRAPHQL
            directive @foo(arg: Int) on FIELD
            
            directive @repeatableFoo(arg: Int) repeatable on FIELD
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Supports descriptions') */
    public function testSupportsDescriptions(): void
    {
        /* TODO add schema description - see https://github.com/webonyx/graphql-php/issues/1027
            """Do you agree that this is the most creative schema ever?"""
            schema {
              query: Query
            }
        */
        $sdl = <<<GRAPHQL
            "This is a directive"
            directive @foo(
              "It has an argument"
              arg: Int
            ) on FIELD
            
            "Who knows what inside this scalar?"
            scalar MysteryScalar
            
            "This is a input object type"
            input FooInput {
              "It has a field"
              field: Int
            }
            
            "This is a interface type"
            interface Energy {
              "It also has a field"
              str: String
            }
            
            "There is nothing inside!"
            union BlackHole

            "With an enum"
            enum Color {
              RED
            
              "Not a creative color"
              GREEN
              BLUE
            }
            
            "What a great type"
            type Query {
              "And a field to boot"
              str: String
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Maintains @include, @skip & @specifiedBy') */
    public function testMaintainsIncludeSkipAndSpecifiedBy(): void
    {
        $schema = BuildSchema::buildAST(Parser::parse('type Query'));

        // TODO switch to 4 when adding @specifiedBy - see https://github.com/webonyx/graphql-php/issues/1140
        self::assertCount(3, $schema->getDirectives());
        self::assertSame(Directive::skipDirective(), $schema->getDirective('skip'));
        self::assertSame(Directive::includeDirective(), $schema->getDirective('include'));
        self::assertSame(Directive::deprecatedDirective(), $schema->getDirective('deprecated'));

        self::markTestIncomplete('See https://github.com/webonyx/graphql-php/issues/1140');
        self::assertSame(Directive::specifiedByDirective(), $schema->getDirective('specifiedBy'));
    }

    /** @see it('Overriding directives excludes specified') */
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

        self::markTestIncomplete('See https://github.com/webonyx/graphql-php/issues/1140');
        self::assertNotEquals(Directive::specifiedByDirective(), $schema->getDirective('specifiedBy'));
    }

    /** @see it('Adding directives maintains @include, @skip & @specifiedBy') */
    public function testAddingDirectivesMaintainsIncludeSkipAndSpecifiedBy(): void
    {
        $sdl = <<<GRAPHQL
            directive @foo(arg: Int) on FIELD
            
            GRAPHQL;
        $schema = BuildSchema::buildAST(Parser::parse($sdl));

        // TODO switch to 5 when adding @specifiedBy - see https://github.com/webonyx/graphql-php/issues/1140
        self::assertCount(4, $schema->getDirectives());
        self::assertNotNull($schema->getDirective('foo'));
        self::assertNotNull($schema->getDirective('skip'));
        self::assertNotNull($schema->getDirective('include'));
        self::assertNotNull($schema->getDirective('deprecated'));

        self::markTestIncomplete('See https://github.com/webonyx/graphql-php/issues/1140');
        self::assertNotNull($schema->getDirective('specifiedBy'));
    }

    /** @see it('Type modifiers') */
    public function testTypeModifiers(): void
    {
        $sdl = <<<GRAPHQL
            type Query {
              nonNullStr: String!
              listOfStrings: [String]
              listOfNonNullStrings: [String!]
              nonNullListOfStrings: [String]!
              nonNullListOfNonNullStrings: [String!]!
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Recursive type') */
    public function testRecursiveType(): void
    {
        $sdl = <<<GRAPHQL
            type Query {
              str: String
              recurse: Query
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Two types circular') */
    public function testTwoTypesCircular(): void
    {
        $sdl = <<<GRAPHQL
            type TypeOne {
              str: String
              typeTwo: TypeTwo
            }
            
            type TypeTwo {
              str: String
              typeOne: TypeOne
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Single argument field') */
    public function testSingleArgumentField(): void
    {
        $sdl = <<<GRAPHQL
            type Query {
              str(int: Int): String
              floatToStr(float: Float): String
              idToStr(id: ID): String
              booleanToStr(bool: Boolean): String
              strToStr(bool: String): String
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Simple type with multiple arguments') */
    public function testSimpleTypeWithMultipleArguments(): void
    {
        $sdl = <<<GRAPHQL
            type Query {
              str(int: Int, bool: Boolean): String
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Empty interface') */
    public function testEmptyInterface(): void
    {
        $sdl = <<<GRAPHQL
            interface EmptyInterface
            
            GRAPHQL;

        $definition = Parser::parse($sdl)->definitions[0];
        self::assertInstanceOf(InterfaceTypeDefinitionNode::class, $definition);
        self::assertCount(0, $definition->interfaces, 'The interfaces property must be an empty list.');

        self::assertCycle($sdl);
    }

    /** @see it('Simple type with interface') */
    public function testSimpleTypeWithInterface(): void
    {
        $sdl = <<<GRAPHQL
            type Query implements WorldInterface {
              str: String
            }
            
            interface WorldInterface {
              str: String
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Simple interface hierarchy') */
    public function testSimpleInterfaceHierarchy(): void
    {
        // `graphql-js` has `query: Child` but that's incorrect as `query` has to be Object type
        $sdl = <<<GRAPHQL
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
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Empty enum') */
    public function testEmptyEnum(): void
    {
        $sdl = <<<GRAPHQL
            enum EmptyEnum
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Simple output enum') */
    public function testSimpleOutputEnum(): void
    {
        $sdl = <<<GRAPHQL
            enum Hello {
              WORLD
            }
            
            type Query {
              hello: Hello
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Simple input enum') */
    public function testSimpleInputEnum(): void
    {
        $sdl = <<<GRAPHQL
            enum Hello {
              WORLD
            }
            
            type Query {
              str(hello: Hello): String
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Multiple value enum') */
    public function testMultipleValueEnum(): void
    {
        $sdl = <<<GRAPHQL
            enum Hello {
              WO
              RLD
            }
            
            type Query {
              hello: Hello
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Empty union') */
    public function testEmptyUnion(): void
    {
        $sdl = <<<GRAPHQL
            union EmptyUnion
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Simple Union') */
    public function testSimpleUnion(): void
    {
        $sdl = <<<GRAPHQL
            union Hello = World
            
            type Query {
              hello: Hello
            }
            
            type World {
              str: String
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Multiple Union') */
    public function testMultipleUnion(): void
    {
        $sdl = <<<GRAPHQL
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
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Can build recursive Union') */
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

    /** @see it('Custom Scalar') */
    public function testCustomScalar(): void
    {
        $sdl = <<<GRAPHQL
            scalar CustomScalar
            
            type Query {
              customScalar: CustomScalar
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Empty Input Object') */
    public function testEmptyInputObject(): void
    {
        $sdl = <<<GRAPHQL
            input EmptyInputObject
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Input Object') */
    public function testInputObject(): void
    {
        $sdl = <<<GRAPHQL
            input Input {
              int: Int
            }
            
            type Query {
              field(in: Input): String
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Simple argument field with default') */
    public function testSimpleArgumentFieldWithDefault(): void
    {
        $sdl = <<<GRAPHQL
            type Query {
              str(int: Int = 2): String
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Custom scalar argument field with default') */
    public function testCustomScalarArgumentFieldWithDefault(): void
    {
        $sdl = <<<GRAPHQL
            scalar CustomScalar
            
            type Query {
              str(int: CustomScalar = 2): String
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Simple type with mutation') */
    public function testSimpleTypeWithMutation(): void
    {
        $sdl = <<<GRAPHQL
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
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Simple type with subscription') */
    public function testSimpleTypeWithSubscription(): void
    {
        $sdl = <<<GRAPHQL
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
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Unreferenced type implementing referenced interface') */
    public function testUnreferencedTypeImplementingReferencedInterface(): void
    {
        $sdl = <<<GRAPHQL
            type Concrete implements Interface {
              key: String
            }
            
            interface Interface {
              key: String
            }
            
            type Query {
              interface: Interface
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Unreferenced interface implementing referenced interface') */
    public function testUnreferencedInterfaceImplementingReferencedInterface(): void
    {
        $sdl = <<<GRAPHQL
            interface Child implements Parent {
              key: String
            }
            
            interface Parent {
              key: String
            }
            
            type Query {
              iface: Parent
            }
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Unreferenced type implementing referenced union') */
    public function testUnreferencedTypeImplementingReferencedUnion(): void
    {
        $sdl = <<<GRAPHQL
            type Concrete {
              key: String
            }
            
            type Query {
              union: Union
            }
            
            union Union = Concrete
            
            GRAPHQL;
        self::assertCycle($sdl);
    }

    /** @see it('Supports @deprecated') */
    public function testSupportsDeprecated(): void
    {
        $sdl = <<<GRAPHQL
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
            
            GRAPHQL;

        self::assertCycle($sdl);

        $ast = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($ast);

        $myEnum = $schema->getType('MyEnum');
        self::assertInstanceOf(EnumType::class, $myEnum);

        $value = $myEnum->getValue('VALUE');
        self::assertInstanceOf(EnumValueDefinition::class, $value);
        self::assertFalse($value->isDeprecated());

        $oldValue = $myEnum->getValue('OLD_VALUE');
        self::assertInstanceOf(EnumValueDefinition::class, $oldValue);
        self::assertTrue($oldValue->isDeprecated());
        self::assertSame('No longer supported', $oldValue->deprecationReason);

        $otherValue = $myEnum->getValue('OTHER_VALUE');
        self::assertInstanceOf(EnumValueDefinition::class, $otherValue);
        self::assertTrue($otherValue->isDeprecated());
        self::assertSame('Terrible reasons', $otherValue->deprecationReason);

        $queryType = $schema->getType('Query');
        self::assertInstanceOf(ObjectType::class, $queryType);

        $rootFields = $queryType->getFields();
        self::assertTrue($rootFields['field1']->isDeprecated());
        self::assertSame('No longer supported', $rootFields['field1']->deprecationReason);

        self::assertTrue($rootFields['field2']->isDeprecated());
        self::assertSame('Because I said so', $rootFields['field2']->deprecationReason);

        $type = $schema->getType('MyInput');
        self::assertInstanceOf(InputObjectType::class, $type);
        $inputFields = $type->getFields();
        self::assertNull($inputFields['newInput']->deprecationReason);
        self::assertSame('No longer supported', $inputFields['oldInput']->deprecationReason);
        self::assertSame('Use newInput', $inputFields['otherInput']->deprecationReason);

        self::assertSame('No longer supported', $rootFields['field3']->args[0]->deprecationReason);
        self::assertSame('Why not?', $rootFields['field4']->args[0]->deprecationReason);
    }

    /** @see it('Supports @specifiedBy') */
    public function testSupportsSpecifiedBy(): void
    {
        self::markTestSkipped('See https://github.com/webonyx/graphql-php/issues/1140');
        $sdl = <<<GRAPHQL
            scalar Foo @specifiedBy(url: "https://example.com/foo_spec")
            
            type Query {
              foo: Foo @deprecated
            }
            
            GRAPHQL;

        self::assertCycle($sdl);

        $schema = BuildSchema::build($sdl);

        self::assertSame('https://example.com/foo_spec', $schema->getType('Foo')->specifiedByURL);
    }

    /** @see it('Correctly extend scalar type') */
    public function testCorrectlyExtendScalarType(): void
    {
        $scalarSDL = <<<'GRAPHQL'
            scalar SomeScalar
            
            extend scalar SomeScalar @foo
            
            extend scalar SomeScalar @bar
            
            GRAPHQL;

        $schema = BuildSchema::build("
            {$scalarSDL}
            directive @foo on SCALAR
            directive @bar on SCALAR
        ");

        $someScalar = $schema->getType('SomeScalar');
        assert($someScalar instanceof ScalarType);

        $expectedSomeScalarSDL = <<<'GRAPHQL'
            scalar SomeScalar
            GRAPHQL;

        self::assertSame($expectedSomeScalarSDL, SchemaPrinter::printType($someScalar));
        self::assertSame($scalarSDL, $this->printAllASTNodes($someScalar));
    }

    /** @see it('Correctly extend object type') */
    public function testCorrectlyExtendObjectType(): void
    {
        $objectSDL = <<<'GRAPHQL'
            type SomeObject implements Foo {
              first: String
            }
            
            extend type SomeObject implements Bar {
              second: Int
            }
            
            extend type SomeObject implements Baz {
              third: Float
            }
            
            GRAPHQL;

        $schema = BuildSchema::build("
            {$objectSDL}
            interface Foo
            interface Bar
            interface Baz
        ");

        $someObject = $schema->getType('SomeObject');
        assert($someObject instanceof ObjectType);

        $expectedSomeObjectSDL = <<<'GRAPHQL'
            type SomeObject implements Foo & Bar & Baz {
              first: String
              second: Int
              third: Float
            }
            GRAPHQL;

        self::assertSame($expectedSomeObjectSDL, SchemaPrinter::printType($someObject));
        self::assertSame($objectSDL, $this->printAllASTNodes($someObject));
    }

    /** @see it('Correctly extend interface type') */
    public function testCorrectlyExtendInterfaceType(): void
    {
        $interfaceSDL = <<<'GRAPHQL'
            interface SomeInterface {
              first: String
            }
            
            extend interface SomeInterface {
              second: Int
            }
            
            extend interface SomeInterface {
              third: Float
            }
            
            GRAPHQL;

        $schema = BuildSchema::build($interfaceSDL);

        $someInterface = $schema->getType('SomeInterface');
        assert($someInterface instanceof InterfaceType);

        $expectedSomeInterfaceSDL = <<<'GRAPHQL'
            interface SomeInterface {
              first: String
              second: Int
              third: Float
            }
            GRAPHQL;

        self::assertSame($expectedSomeInterfaceSDL, SchemaPrinter::printType($someInterface));
        self::assertSame($interfaceSDL, $this->printAllASTNodes($someInterface));
    }

    /** @see it('Correctly extend union type') */
    public function testCorrectlyExtendUnionType(): void
    {
        $unionSDL = <<<'GRAPHQL'
            union SomeUnion = FirstType
            
            extend union SomeUnion = SecondType
            
            extend union SomeUnion = ThirdType
            
            GRAPHQL;

        $schema = BuildSchema::build("
            {$unionSDL}
            type FirstType
            type SecondType
            type ThirdType
        ");

        $someUnion = $schema->getType('SomeUnion');
        assert($someUnion instanceof UnionType);

        $expectedSomeUnionSDL = <<<'GRAPHQL'
            union SomeUnion = FirstType | SecondType | ThirdType
            GRAPHQL;

        self::assertSame($expectedSomeUnionSDL, SchemaPrinter::printType($someUnion));
        self::assertSame($unionSDL, $this->printAllASTNodes($someUnion));
    }

    /** @see it('Correctly extend enum type') */
    public function testCorrectlyExtendEnumType(): void
    {
        $enumSDL = <<<'GRAPHQL'
            enum SomeEnum {
              FIRST
            }
            
            extend enum SomeEnum {
              SECOND
            }
            
            extend enum SomeEnum {
              THIRD
            }
            
            GRAPHQL;

        $schema = BuildSchema::build($enumSDL);

        $someEnum = $schema->getType('SomeEnum');
        assert($someEnum instanceof EnumType);

        $expectedSomeEnumSDL = <<<'GRAPHQL'
            enum SomeEnum {
              FIRST
              SECOND
              THIRD
            }
            GRAPHQL;

        self::assertSame($expectedSomeEnumSDL, SchemaPrinter::printType($someEnum));
        self::assertSame($enumSDL, $this->printAllASTNodes($someEnum));
    }

    /** @see it('Correctly extend input object type') */
    public function testCorrectlyExtendInputObjectType(): void
    {
        $inputSDL = <<<'GRAPHQL'
            input SomeInput {
              first: String
            }
            
            extend input SomeInput {
              second: Int
            }
            
            extend input SomeInput {
              third: Float
            }
            
            GRAPHQL;

        $schema = BuildSchema::build($inputSDL);

        $someInput = $schema->getType('SomeInput');
        assert($someInput instanceof InputObjectType);

        $expectedSomeInputSDL = <<<'GRAPHQL'
            input SomeInput {
              first: String
              second: Int
              third: Float
            }
            GRAPHQL;

        self::assertSame($expectedSomeInputSDL, SchemaPrinter::printType($someInput));
        self::assertSame($inputSDL, $this->printAllASTNodes($someInput));
    }

    /** @see it('Correctly assign AST nodes') */
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

        $schemaAst = $schema->astNode;
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

        self::assertSame([
            $schemaAst,
            $queryAst,
            $testInputAst,
            $testEnumAst,
            $testUnionAst,
            $testInterfaceAst,
            $testTypeAst,
            $testScalarAst,
            $testDirectiveAst,
        ], iterator_to_array($ast->definitions));

        $testField = $query->getField('testField');
        self::assertASTMatches('testField(testArg: TestInput): TestUnion', $testField->astNode);
        self::assertASTMatches('testArg: TestInput', $testField->args[0]->astNode);
        self::assertASTMatches('testInputField: TestEnum', $testInput->getField('testInputField')->astNode);
        self::assertASTMatches('TEST_VALUE', $testEnum->getValue('TEST_VALUE')->astNode ?? null);
        self::assertASTMatches('interfaceField: String', $testInterface->getField('interfaceField')->astNode);
        self::assertASTMatches('interfaceField: String', $testType->getField('interfaceField')->astNode);
        self::assertASTMatches('arg: TestScalar', $testDirective->args[0]->astNode);
    }

    /** @see it('Root operation types with custom names') */
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

        $query = $schema->getQueryType();
        self::assertInstanceOf(ObjectType::class, $query);
        self::assertSame('SomeQuery', $query->name);

        $mutation = $schema->getMutationType();
        self::assertInstanceOf(ObjectType::class, $mutation);
        self::assertSame('SomeMutation', $mutation->name);

        $subscription = $schema->getSubscriptionType();
        self::assertInstanceOf(ObjectType::class, $subscription);
        self::assertSame('SomeSubscription', $subscription->name);
    }

    /** @see it('Default root operation type names') */
    public function testDefaultRootOperationTypeNames(): void
    {
        $schema = BuildSchema::build('
            type Query
            type Mutation
            type Subscription
        ');

        $query = $schema->getQueryType();
        self::assertInstanceOf(ObjectType::class, $query);
        self::assertSame('Query', $query->name);

        $mutation = $schema->getMutationType();
        self::assertInstanceOf(ObjectType::class, $mutation);
        self::assertSame('Mutation', $mutation->name);

        $subscription = $schema->getSubscriptionType();
        self::assertInstanceOf(ObjectType::class, $subscription);
        self::assertSame('Subscription', $subscription->name);
    }

    /** @see it('can build invalid schema') */
    public function testCanBuildInvalidSchema(): void
    {
        // Invalid schema, because it is missing query root type
        $schema = BuildSchema::build('type Mutation');
        $errors = $schema->validate();
        self::assertNotEmpty($errors);
    }

    /** @see it('Do not override standard types') */
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

    /** @see it('Allows to reference introspection types') */
    public function testAllowsToReferenceIntrospectionTypes(): void
    {
        $schema = BuildSchema::build('
            type Query {
              introspectionField: __EnumValue
            }
        ');

        $queryType = $schema->getQueryType();
        self::assertNotNull($queryType);
        $type = $queryType->getField('introspectionField')->getType();
        self::assertInstanceOf(ObjectType::class, $type);
        self::assertSame('__EnumValue', $type->name);
        self::assertSame(Introspection::_enumValue(), $schema->getType('__EnumValue'));
    }

    /** @see it('Rejects invalid SDL') */
    public function testRejectsInvalidSDL(): void
    {
        $doc = Parser::parse('
            type Query {
              foo: String @unknown
            }
        ');

        $this->expectException(Error::class);
        $this->expectExceptionMessage(KnownDirectives::unknownDirectiveMessage('unknown'));
        BuildSchema::build($doc);
    }

    /** @see it('Allows to disable SDL validation') */
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

    /** @see it('Throws on unknown types') */
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

    /** it('correctly processes viral schema', () => {. */
    public function testCorrectlyProcessesViralSchema(): void
    {
        $schema = BuildSchema::build(\Safe\file_get_contents(__DIR__ . '/../viralSchema.graphql'));

        $queryType = $schema->getQueryType();
        self::assertNotNull($queryType);
        self::assertSame('Query', $queryType->name);
        self::assertNotNull($schema->getType('Virus'));
        self::assertNotNull($schema->getType('Mutation'));
        // Though the viral schema has a 'Mutation' type, it is not used for the 'mutation' operation.
        self::assertNull($schema->getMutationType());
    }

    /** @see https://github.com/webonyx/graphql-php/issues/997 */
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

        /** @var array<int, string> $decorated */
        $decorated = [];
        /** @var array<int, array{array<string, mixed>, Node&TypeDefinitionNode, array<string, mixed>}> $calls */
        $calls = [];

        $typeConfigDecorator = static function (array $defaultConfig, TypeDefinitionNode $node, array $allNodesMap) use (&$decorated, &$calls) {
            $decorated[] = $defaultConfig['name'];
            $calls[] = [$defaultConfig, $node, $allNodesMap];

            return ['description' => 'My description of ' . $node->getName()->value] + $defaultConfig;
        };

        $fieldResolver = static fn (): string => 'OK';
        $fieldConfigDecorator = static function (array $defaultConfig, FieldDefinitionNode $node) use (&$fieldResolver): array {
            $defaultConfig['resolve'] = $fieldResolver;

            /** @var UnnamedFieldDefinitionConfig $defaultConfig */
            return $defaultConfig;
        };

        $schema = BuildSchema::buildAST($doc, $typeConfigDecorator, [], $fieldConfigDecorator);
        $schema->getTypeMap();
        self::assertSame(['Query', 'Color', 'Hello'], $decorated);

        self::assertArrayHasKey(0, $calls);
        [$defaultConfig, $node, $allNodesMap] = $calls[0]; // type Query
        self::assertInstanceOf(ObjectTypeDefinitionNode::class, $node);
        self::assertSame('Query', $defaultConfig['name']);
        self::assertInstanceOf(\Closure::class, $defaultConfig['fields']);
        self::assertInstanceOf(\Closure::class, $defaultConfig['interfaces']);
        self::assertArrayHasKey('description', $defaultConfig);
        self::assertCount(6, $defaultConfig);
        self::assertSame(['Query', 'Color', 'Hello'], array_keys($allNodesMap));

        $query = $schema->getType('Query');
        self::assertInstanceOf(ObjectType::class, $query);
        self::assertSame('My description of Query', $query->description);

        self::assertSame($fieldResolver, $query->getFields()['str']->resolveFn);
        self::assertSame($fieldResolver, $query->getFields()['color']->resolveFn);
        self::assertSame($fieldResolver, $query->getFields()['hello']->resolveFn);

        self::assertArrayHasKey(1, $calls);
        [$defaultConfig, $node, $allNodesMap] = $calls[1]; // enum Color
        self::assertInstanceOf(EnumTypeDefinitionNode::class, $node);
        self::assertSame('Color', $defaultConfig['name']);
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
        self::assertCount(5, $defaultConfig); // 3 + astNode + extensionASTNodes
        self::assertSame(['Query', 'Color', 'Hello'], array_keys($allNodesMap));

        $color = $schema->getType('Color');
        self::assertInstanceOf(EnumType::class, $color);
        self::assertSame('My description of Color', $color->description);

        self::assertArrayHasKey(2, $calls);
        [$defaultConfig, $node, $allNodesMap] = $calls[2]; // interface Hello
        self::assertInstanceOf(InterfaceTypeDefinitionNode::class, $node);
        self::assertSame('Hello', $defaultConfig['name']);
        self::assertInstanceOf(\Closure::class, $defaultConfig['fields']);
        self::assertArrayHasKey('description', $defaultConfig);
        self::assertArrayHasKey('interfaces', $defaultConfig);
        self::assertCount(6, $defaultConfig);
        self::assertSame(['Query', 'Color', 'Hello'], array_keys($allNodesMap));

        $hello = $schema->getType('Hello');
        self::assertInstanceOf(InterfaceType::class, $hello);
        self::assertSame('My description of Hello', $hello->description);
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
        self::assertSame(['Query'], $created);

        $schema->getType('Color');
        /** @phpstan-ignore staticMethod.impossibleType */
        self::assertSame(['Query', 'Color'], $created);

        $schema->getType('Hello');
        /** @phpstan-ignore staticMethod.impossibleType */
        self::assertSame(['Query', 'Color', 'Hello'], $created);

        $types = $schema->getTypeMap();
        /** @phpstan-ignore staticMethod.impossibleType */
        self::assertSame(['Query', 'Color', 'Hello', 'World'], $created);

        self::assertArrayHasKey('Query', $types);
        self::assertArrayHasKey('Color', $types);
        self::assertArrayHasKey('Hello', $types);
        self::assertArrayHasKey('World', $types);
    }

    /**
     * @param array<string> $sdlExts
     * @param callable(\GraphQL\Type\Definition\Type $type):void $assert
     *
     * @dataProvider correctlyExtendsTypesDataProvider
     */
    public function testCorrectlyExtendsTypes(string $baseSdl, array $sdlExts, string $expectedSdl, callable $assert): void
    {
        $defaultSdl = <<<'GRAPHQL'
            directive @foo on SCHEMA | SCALAR | OBJECT | INTERFACE | UNION | ENUM | INPUT_OBJECT
            interface Bar
            GRAPHQL;

        $sdl = implode("\n", [$defaultSdl, $baseSdl, ...$sdlExts]);
        $schema = BuildSchema::build($sdl);
        $myType = $schema->getType('MyType');
        self::assertNotNull($myType);
        self::assertSame($expectedSdl, SchemaPrinter::printType($myType));
        self::assertCount(count($sdlExts), $myType->extensionASTNodes);
        $assert($myType);
    }

    /** @return iterable<array<mixed>> */
    public static function correctlyExtendsTypesDataProvider(): iterable
    {
        yield 'scalar' => [
            'scalar MyType',
            [
                <<<'GRAPHQL'
                extend scalar MyType @foo
                GRAPHQL,
            ],
            'scalar MyType',
            function (ScalarType $type): void {
                // nothing else to assert here, scalar extensions can add only directives
            },
        ];
        yield 'object' => [
            'type MyType',
            [
                <<<'GRAPHQL'
                extend type MyType @foo
                GRAPHQL,
                <<<'GRAPHQL'
                extend type MyType {
                  field: String
                }
                GRAPHQL,
                <<<'GRAPHQL'
                extend type MyType implements Bar
                GRAPHQL,
            ],
            <<<'GRAPHQL'
            type MyType implements Bar {
              field: String
            }
            GRAPHQL,
            function (ObjectType $type): void {
                self::assertInstanceOf(StringType::class, $type->getField('field')->getType());
                self::assertTrue($type->implementsInterface(new InterfaceType(['name' => 'Bar', 'fields' => []])));
            },
        ];
        yield 'interface' => [
            'interface MyType',
            [
                <<<'GRAPHQL'
                extend interface MyType @foo
                GRAPHQL,
                <<<'GRAPHQL'
                extend interface MyType {
                  field: String
                }
                GRAPHQL,
                <<<'GRAPHQL'
                extend interface MyType implements Bar
                GRAPHQL,
            ],
            <<<'GRAPHQL'
            interface MyType implements Bar {
              field: String
            }
            GRAPHQL,
            function (InterfaceType $type): void {
                self::assertInstanceOf(StringType::class, $type->getField('field')->getType());
                self::assertTrue($type->implementsInterface(new InterfaceType(['name' => 'Bar', 'fields' => []])));
            },
        ];
        yield 'union' => [
            'union MyType',
            [
                <<<'GRAPHQL'
                extend union MyType @foo
                GRAPHQL,
                <<<'GRAPHQL'
                extend union MyType = Bar
                GRAPHQL,
            ],
            'union MyType = Bar',
            function (UnionType $type): void {
                self::assertCount(1, $type->getTypes());
            },
        ];
        yield 'enum' => [
            'enum MyType',
            [
                <<<'GRAPHQL'
                extend enum MyType @foo
                GRAPHQL,
                <<<'GRAPHQL'
                extend enum MyType {
                  X
                }
                GRAPHQL,
                <<<'GRAPHQL'
                extend enum MyType {
                  Y
                }
                GRAPHQL,
            ],
            <<<'GRAPHQL'
            enum MyType {
              X
              Y
            }
            GRAPHQL,
            function (EnumType $type): void {
                self::assertNotNull($type->getValue('X'));
                self::assertNotNull($type->getValue('Y'));
            },
        ];
        yield 'input' => [
            'input MyType',
            [
                <<<'GRAPHQL'
                extend input MyType @foo
                GRAPHQL,
                <<<'GRAPHQL'
                extend input MyType {
                  field: String
                }
                GRAPHQL,
            ],
            <<<'GRAPHQL'
            input MyType {
              field: String
            }
            GRAPHQL,
            function (InputObjectType $type): void {
                self::assertInstanceOf(StringType::class, $type->getField('field')->getType());
            },
        ];
    }
}
