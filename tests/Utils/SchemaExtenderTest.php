<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SerializationError;
use GraphQL\Error\SyntaxError;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Tests\Utils\SchemaExtenderTest\SomeInterfaceClassType;
use GraphQL\Tests\Utils\SchemaExtenderTest\SomeObjectClassType;
use GraphQL\Tests\Utils\SchemaExtenderTest\SomeScalarClassType;
use GraphQL\Tests\Utils\SchemaExtenderTest\SomeUnionClassType;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaExtender;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Validator\Rules\KnownDirectives;

/** @phpstan-import-type UnnamedFieldDefinitionConfig from FieldDefinition */
final class SchemaExtenderTest extends TestCaseBase
{
    /** @param NamedType|Schema $obj */
    private function printExtensionNodes($obj): string
    {
        assert(isset($obj->extensionASTNodes));

        return Printer::doPrint(new DocumentNode([
            'definitions' => new NodeList($obj->extensionASTNodes),
        ]));
    }

    /**
     * @throws \JsonException
     * @throws Error
     * @throws InvariantViolation
     * @throws SerializationError
     * @throws SyntaxError
     *
     * @return array<string>
     */
    private static function schemaDefinitions(Schema $schema): array
    {
        $definitions = [];
        foreach (Parser::parse(SchemaPrinter::doPrint($schema))->definitions as $node) {
            $definitions[] = Printer::doPrint($node);
        }

        return $definitions;
    }

    /**
     * @throws \JsonException
     * @throws Error
     * @throws InvariantViolation
     * @throws SerializationError
     * @throws SyntaxError
     */
    private static function printSchemaChanges(Schema $schema, Schema $extendedSchema): string
    {
        $schemaDefinitions = self::schemaDefinitions($schema);
        $extendedDefinitions = self::schemaDefinitions($extendedSchema);

        $changed = array_diff($extendedDefinitions, $schemaDefinitions);

        return implode("\n\n", $changed);
    }

    /**
     * graphql-js uses printASTNode() everywhere, but our Schema doesn't have astNode property,
     * hence this helper method that calls getAstNode() instead.
     */
    private function printASTSchema(Schema $schema): string
    {
        $astNode = $schema->astNode;
        assert($astNode instanceof SchemaDefinitionNode);

        return Printer::doPrint($astNode);
    }

    /**
     * @throws \GraphQL\Error\SerializationError
     * @throws \JsonException
     * @throws Error
     * @throws InvariantViolation
     */
    private static function assertSchemaEquals(Schema $expectedSchema, Schema $actualSchema): void
    {
        self::assertSame(
            SchemaPrinter::doPrint($expectedSchema),
            SchemaPrinter::doPrint($actualSchema),
        );
    }

    /** @see it('returns the original schema when there are no type definitions') */
    public function testReturnsTheOriginalSchemaWhenThereAreNoTypeDefinitions(): void
    {
        $schema = BuildSchema::build('type Query');
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('{ field }'));
        self::assertSchemaEquals($schema, $extendedSchema);
    }

    /** @see it('can be used for limited execution') */
    public function testCanBeUsedForLimitedExecution(): void
    {
        $schema = BuildSchema::build('type Query');
        $extendAST = Parser::parse('
          extend type Query {
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        $result = GraphQL::executeQuery(
            $extendedSchema,
            '{ newField }',
            ['newField' => 123]
        );

        self::assertSame(
            [
                'data' => [
                    'newField' => '123',
                ],
            ],
            $result->toArray(),
        );
    }

    /** @see it('extends objects by adding new fields') */
    public function testExtendsObjectsByAddingNewFields(): void
    {
        $schema = BuildSchema::build('
          type Query {
            someObject: SomeObject
          }

          type SomeObject implements AnotherInterface & SomeInterface {
            self: SomeObject
            tree: [SomeObject]!
            "Old field description."
            oldField: String
          }

          interface SomeInterface {
            self: SomeInterface
          }

          interface AnotherInterface {
            self: SomeObject
          }
        ');
        $extensionSDL = '
          extend type SomeObject {
            "New field description."
            newField(arg: Boolean): String
          }
        ';
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
type SomeObject implements AnotherInterface & SomeInterface {
  self: SomeObject
  tree: [SomeObject]!
  "Old field description."
  oldField: String
  "New field description."
  newField(arg: Boolean): String
}
GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema),
        );
    }

    /** @see it('describes extended fields with strings when present') */
    public function testDescribesExtendedFieldsWithStringsWhenPresent(): void
    {
        $schema = BuildSchema::build('type Query');
        $extendAST = Parser::parse('
          extend type Query {
            # New field description.
            "Actually use this description."
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST, ['commentDescriptions' => true]);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              type Query {
                "Actually use this description."
                newField: String
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema),
        );
    }

    /** @see it('ignores comment description on extended fields if location is not provided') */
    public function testIgnoresCommentDescriptionOnExtendedFieldsIfLocationIsNotProvided(): void
    {
        $schema = BuildSchema::build('type Query');
        $extendSDL = '
          extend type Query {
            # New field description.
            newField: String
          }
        ';
        $extendAST = Parser::parse($extendSDL, ['noLocation' => true]);
        $extendedSchema = SchemaExtender::extend($schema, $extendAST, ['commentDescriptions' => true]);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              type Query {
                newField: String
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema),
        );
    }

    /** @see it('extends objects with standard type fields') */
    public function testExtendsObjectsWithStandardTypeFields(): void
    {
        // @phpstan-ignore-next-line
        $this->markTestSkipped('See https://github.com/webonyx/graphql-php/issues/964');

        $schema = BuildSchema::build('type Query');

        // String and Boolean are always included through introspection types
        self::assertNull($schema->getType('Int'));
        self::assertNull($schema->getType('Float'));
        self::assertSame(Type::string(), $schema->getType('String'));
        self::assertSame(Type::boolean(), $schema->getType('Boolean'));
        self::assertNull($schema->getType('ID'));

        $extendAST = Parser::parse('
          extend type Query {
            bool: Boolean
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertNull($extendedSchema->getType('Int'));
        self::assertNull($extendedSchema->getType('Float'));
        self::assertSame(Type::string(), $extendedSchema->getType('String'));
        self::assertSame(Type::boolean(), $extendedSchema->getType('Boolean'));
        self::assertNull($extendedSchema->getType('ID'));

        $extendTwiceAST = Parser::parse('
          extend type Query {
            int: Int
            float: Float
            id: ID
          }
        ');
        $extendedTwiceSchema = SchemaExtender::extend($schema, $extendTwiceAST);

        self::assertEmpty($extendedTwiceSchema->validate());
        self::assertSame(Type::int(), $extendedTwiceSchema->getType('Int'));
        self::assertSame(Type::float(), $extendedTwiceSchema->getType('Float'));
        self::assertSame(Type::string(), $extendedTwiceSchema->getType('String'));
        self::assertSame(Type::boolean(), $extendedTwiceSchema->getType('Boolean'));
        self::assertSame(Type::id(), $extendedTwiceSchema->getType('ID'));
    }

    /** @see it('extends enums by adding new values') */
    public function testExtendsEnumsByAddingNewValues(): void
    {
        $schema = BuildSchema::build('
          type Query {
            someEnum(arg: SomeEnum): SomeEnum
          }

          directive @foo(arg: SomeEnum) on SCHEMA

          enum SomeEnum {
            "Old value description."
            OLD_VALUE
          }
        ');
        $extendAST = Parser::parse('
          extend enum SomeEnum {
            "New value description."
            NEW_VALUE
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              enum SomeEnum {
                "Old value description."
                OLD_VALUE
                "New value description."
                NEW_VALUE
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema),
        );
    }

    /** @see it('extends unions by adding new types') */
    public function testExtendsUnionsByAddingNewTypes(): void
    {
        $schema = BuildSchema::build('
          type Query {
            someUnion: SomeUnion
          }

          union SomeUnion = Foo | Biz

          type Foo { foo: String }
          type Biz { biz: String }
          type Bar { bar: String }
        ');
        $extendAST = Parser::parse('
          extend union SomeUnion = Bar
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              union SomeUnion = Foo | Biz | Bar
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema),
        );
    }

    /** @see it('allows extension of union by adding itself') */
    public function testAllowsExtensionOfUnionByAddingItself(): void
    {
        $schema = BuildSchema::build('
          union SomeUnion
        ');
        $extendAST = Parser::parse('
          extend union SomeUnion = SomeUnion
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertGreaterThan(0, count($extendedSchema->validate()));
        self::assertSame(
            <<<GRAPHQL
                union SomeUnion = SomeUnion
                GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema),
        );
    }

    /** @see it('extends inputs by adding new fields') */
    public function testExtendsInputsByAddingNewFields(): void
    {
        $schema = BuildSchema::build('
          type Query {
            someInput(arg: SomeInput): String
          }

          directive @foo(arg: SomeInput) on SCHEMA

          input SomeInput {
            "Old field description."
            oldField: String
          }
        ');
        $extendAST = Parser::parse('
          extend input SomeInput {
            "New field description."
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              input SomeInput {
                "Old field description."
                oldField: String
                "New field description."
                newField: String
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema),
        );
    }

    /** @see it('extends scalars by adding new directives') */
    public function testExtendsScalarsByAddingNewDirectives(): void
    {
        $schema = BuildSchema::build('
          type Query {
            someScalar(arg: SomeScalar): SomeScalar
          }

          directive @foo(arg: SomeScalar) on SCALAR

          input FooInput {
            foo: SomeScalar
          }

          scalar SomeScalar
        ');
        $extensionSDL = <<<GRAPHQL
          extend scalar SomeScalar @foo

          GRAPHQL;
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));
        $someScalar = $extendedSchema->getType('SomeScalar');
        assert($someScalar instanceof ScalarType);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            $extensionSDL,
            $this->printExtensionNodes($someScalar),
        );
    }

    /** @see it('extends scalars by adding specifiedBy directive') */
    public function testExtendsScalarsByAddingSpecifiedByDirective(): void
    {
        // @phpstan-ignore-next-line
        $this->markTestSkipped('See https://github.com/webonyx/graphql-php/issues/1140');
        $schema = BuildSchema::build('
          type Query {
            foo: Foo
          }

          scalar Foo

          directive @foo on SCALAR
        ');
        $extensionSDL = <<<GRAPHQL
          extend scalar Foo @foo

          extend scalar Foo @specifiedBy(url: "https://example.com/foo_spec")
          GRAPHQL;
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));
        $foo = $extendedSchema->getType('Foo');

        self::assertSame('https://example.com/foo_spec', $foo->specifiedByURL);
        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            $extensionSDL,
            $this->printExtensionNodes($foo),
        );
    }

    /** @see it('correctly assign AST nodes to new and extended types') */
    public function testCorrectlyAssignASTNodesToNewAndExtendedTypes(): void
    {
        $schema = BuildSchema::build('
          type Query

          scalar SomeScalar
          enum SomeEnum
          union SomeUnion
          input SomeInput
          type SomeObject
          interface SomeInterface

          directive @foo on SCALAR
        ');
        $firstExtensionAST = Parser::parse('
          extend type Query {
            newField(testArg: TestInput): TestEnum
          }

          extend scalar SomeScalar @foo

          extend enum SomeEnum {
            NEW_VALUE
          }

          extend union SomeUnion = SomeObject

          extend input SomeInput {
            newField: String
          }

          extend interface SomeInterface {
            newField: String
          }

          input TestInput {
            testInputField: TestEnum
          }

          enum TestEnum {
            TEST_VALUE
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $firstExtensionAST);

        $secondExtensionAST = Parser::parse('
          extend type Query {
            oneMoreNewField: TestUnion
          }

          extend scalar SomeScalar @test

          extend enum SomeEnum {
            ONE_MORE_NEW_VALUE
          }

          extend union SomeUnion = TestType

          extend input SomeInput {
            oneMoreNewField: String
          }

          extend interface SomeInterface {
            oneMoreNewField: String
          }

          union TestUnion = TestType

          interface TestInterface {
            interfaceField: String
          }

          type TestType implements TestInterface {
            interfaceField: String
          }

          directive @test(arg: Int) repeatable on FIELD | SCALAR
        ');
        $extendedTwiceSchema = SchemaExtender::extend(
            $extendedSchema,
            $secondExtensionAST,
        );

        $extendedInOneGoSchema = SchemaExtender::extend(
            $schema,
            AST::concatAST([$firstExtensionAST, $secondExtensionAST]),
        );

        self::assertSchemaEquals($extendedInOneGoSchema, $extendedTwiceSchema);

        $query = $extendedTwiceSchema->getQueryType();
        assert($query instanceof ObjectType);

        $someScalar = $extendedTwiceSchema->getType('SomeScalar');
        assert($someScalar instanceof ScalarType);

        $someEnum = $extendedTwiceSchema->getType('SomeEnum');
        assert($someEnum instanceof EnumType);

        $someUnion = $extendedTwiceSchema->getType('SomeUnion');
        assert($someUnion instanceof UnionType);

        $someInput = $extendedTwiceSchema->getType('SomeInput');
        assert($someInput instanceof InputObjectType);

        $someInterface = $extendedTwiceSchema->getType('SomeInterface');
        assert($someInterface instanceof InterfaceType);

        $testInput = $extendedTwiceSchema->getType('TestInput');
        assert($testInput instanceof InputObjectType);

        $testEnum = $extendedTwiceSchema->getType('TestEnum');
        assert($testEnum instanceof EnumType);

        $testUnion = $extendedTwiceSchema->getType('TestUnion');
        assert($testUnion instanceof UnionType);

        $testInterface = $extendedTwiceSchema->getType('TestInterface');
        assert($testInterface instanceof InterfaceType);

        $testType = $extendedTwiceSchema->getType('TestType');
        assert($testType instanceof ObjectType);

        $testDirective = $extendedTwiceSchema->getDirective('test');
        assert($testDirective instanceof Directive);

        self::assertCount(2, $query->extensionASTNodes);
        self::assertCount(2, $someScalar->extensionASTNodes);
        self::assertCount(2, $someEnum->extensionASTNodes);
        self::assertCount(2, $someUnion->extensionASTNodes);
        self::assertCount(2, $someInput->extensionASTNodes);
        self::assertCount(2, $someInterface->extensionASTNodes);

        self::assertCount(0, $testInput->extensionASTNodes);
        self::assertCount(0, $testEnum->extensionASTNodes);
        self::assertCount(0, $testUnion->extensionASTNodes);
        self::assertCount(0, $testInterface->extensionASTNodes);
        self::assertCount(0, $testType->extensionASTNodes);

        self::assertNotNull($testInput->astNode);
        self::assertNotNull($testEnum->astNode);
        self::assertNotNull($testUnion->astNode);
        self::assertNotNull($testInterface->astNode);
        self::assertNotNull($testType->astNode);
        self::assertNotNull($testDirective->astNode);

        $restoredExtensionAST = new DocumentNode([
            'definitions' => new NodeList([
                $testInput->astNode,
                $testEnum->astNode,
                $testUnion->astNode,
                $testInterface->astNode,
                $testType->astNode,
                $testDirective->astNode,
                ...$query->extensionASTNodes,
                ...$someScalar->extensionASTNodes,
                ...$someEnum->extensionASTNodes,
                ...$someUnion->extensionASTNodes,
                ...$someInput->extensionASTNodes,
                ...$someInterface->extensionASTNodes,
            ]),
        ]);

        self::assertSchemaEquals(
            SchemaExtender::extend($schema, $restoredExtensionAST),
            $extendedTwiceSchema
        );

        self::assertASTMatches('newField(testArg: TestInput): TestEnum', $query->getField('newField')->astNode);
        self::assertASTMatches('testArg: TestInput', $query->getField('newField')->args[0]->astNode);
        self::assertASTMatches('oneMoreNewField: TestUnion', $query->getField('oneMoreNewField')->astNode);
        self::assertASTMatches('NEW_VALUE', $someEnum->getValue('NEW_VALUE')->astNode ?? null);
        self::assertASTMatches('ONE_MORE_NEW_VALUE', $someEnum->getValue('ONE_MORE_NEW_VALUE')->astNode ?? null);
        self::assertASTMatches('newField: String', $someInput->getField('newField')->astNode);
        self::assertASTMatches('oneMoreNewField: String', $someInput->getField('oneMoreNewField')->astNode);
        self::assertASTMatches('newField: String', $someInterface->getField('newField')->astNode);
        self::assertASTMatches('oneMoreNewField: String', $someInterface->getField('oneMoreNewField')->astNode);
        self::assertASTMatches('testInputField: TestEnum', $testInput->getField('testInputField')->astNode);
        self::assertASTMatches('TEST_VALUE', $testEnum->getValue('TEST_VALUE')->astNode ?? null);
        self::assertASTMatches('interfaceField: String', $testInterface->getField('interfaceField')->astNode);
        self::assertASTMatches('interfaceField: String', $testType->getField('interfaceField')->astNode);
        self::assertASTMatches('arg: Int', $testDirective->args[0]->astNode);
    }

    /** @see it('builds types with deprecated fields/values') */
    public function testBuildsTypesWithDeprecatedFieldsOrValues(): void
    {
        $schema = new Schema([]);
        $extendAST = Parser::parse('
          type SomeObject {
            deprecatedField(deprecatedArg: String @deprecated(reason: "unusable")): String @deprecated(reason: "not used anymore")
          }

          enum SomeEnum {
            DEPRECATED_VALUE @deprecated(reason: "do not use")
          }

          input SomeInputObject {
            deprecatedField: String @deprecated(reason: "redundant")
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        $someType = $extendedSchema->getType('SomeObject');
        assert($someType instanceof ObjectType);

        $deprecatedFieldDef = $someType->getField('deprecatedField');

        self::assertTrue($deprecatedFieldDef->isDeprecated());
        self::assertSame('not used anymore', $deprecatedFieldDef->deprecationReason);

        $deprecatedArgument = $deprecatedFieldDef->getArg('deprecatedArg');

        self::assertInstanceOf(Argument::class, $deprecatedArgument);
        self::assertTrue($deprecatedArgument->isDeprecated());
        self::assertSame('unusable', $deprecatedArgument->deprecationReason);

        $someEnum = $extendedSchema->getType('SomeEnum');
        assert($someEnum instanceof EnumType);

        $deprecatedEnumDef = $someEnum->getValue('DEPRECATED_VALUE');
        assert($deprecatedEnumDef instanceof EnumValueDefinition);

        self::assertTrue($deprecatedEnumDef->isDeprecated());
        self::assertSame('do not use', $deprecatedEnumDef->deprecationReason);

        $someInput = $extendedSchema->getType('SomeInputObject');
        assert($someInput instanceof InputObjectType);

        $deprecatedInputField = $someInput->getField('deprecatedField');

        self::assertTrue($deprecatedInputField->isDeprecated());
        self::assertSame('redundant', $deprecatedInputField->deprecationReason);
    }

    /** @see it('extends objects with deprecated fields') */
    public function testExtendsObjectsWithDeprecatedFields(): void
    {
        $schema = BuildSchema::build('type SomeObject');
        $extendAST = Parser::parse('
          extend type SomeObject {
            deprecatedField: String @deprecated(reason: "not used anymore")
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        $someType = $extendedSchema->getType('SomeObject');
        assert($someType instanceof ObjectType);

        $deprecatedFieldDef = $someType->getField('deprecatedField');

        self::assertTrue($deprecatedFieldDef->isDeprecated());
        self::assertSame('not used anymore', $deprecatedFieldDef->deprecationReason);
    }

    /** @see it('extends enums with deprecated values') */
    public function testExtendsEnumsWithDeprecatedValues(): void
    {
        $schema = BuildSchema::build('enum SomeEnum');
        $extendAST = Parser::parse('
          extend enum SomeEnum {
            DEPRECATED_VALUE @deprecated(reason: "do not use")
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        $someEnum = $extendedSchema->getType('SomeEnum');
        assert($someEnum instanceof EnumType);

        $deprecatedEnumDef = $someEnum->getValue('DEPRECATED_VALUE');
        assert($deprecatedEnumDef instanceof EnumValueDefinition);

        self::assertTrue($deprecatedEnumDef->isDeprecated());
        self::assertSame('do not use', $deprecatedEnumDef->deprecationReason);
    }

    /** @see it('adds new unused types') */
    public function testAddsNewUnusedTypes(): void
    {
        $schema = BuildSchema::build('
            type Query {
              dummy: String
            }
        ');
        $extensionSDL = <<<GRAPHQL
            type DummyUnionMember {
              someField: String
            }

            enum UnusedEnum {
              SOME_VALUE
            }

            input UnusedInput {
              someField: String
            }

            interface UnusedInterface {
              someField: String
            }

            type UnusedObject {
              someField: String
            }

            union UnusedUnion = DummyUnionMember
            GRAPHQL;
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            $extensionSDL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('extends objects by adding new fields with arguments') */
    public function testExtendsObjectsByAddingNewFieldsWithArguments(): void
    {
        $schema = BuildSchema::build('
          type SomeObject

          type Query {
            someObject: SomeObject
          }
        ');
        $extendAST = Parser::parse('
          input NewInputObj {
            field1: Int
            field2: [Float]
            field3: String!
          }

          extend type SomeObject {
            newField(arg1: String, arg2: NewInputObj!): String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              type SomeObject {
                newField(arg1: String, arg2: NewInputObj!): String
              }

              input NewInputObj {
                field1: Int
                field2: [Float]
                field3: String!
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('extends objects by adding new fields with existing types') */
    public function testExtendsObjectsByAddingNewFieldsWithExistingTypes(): void
    {
        $schema = BuildSchema::build('
          type Query {
            someObject: SomeObject
          }

          type SomeObject
          enum SomeEnum { VALUE }
        ');
        $extendAST = Parser::parse('
          extend type SomeObject {
            newField(arg1: SomeEnum!): SomeEnum
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              type SomeObject {
                newField(arg1: SomeEnum!): SomeEnum
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('extends objects by adding implemented interfaces') */
    public function testExtendsObjectsByAddingImplementedInterfaces(): void
    {
        $schema = BuildSchema::build('
          type Query {
            someObject: SomeObject
          }

          type SomeObject {
            foo: String
          }

          interface SomeInterface {
            foo: String
          }
        ');
        $extendAST = Parser::parse('
          extend type SomeObject implements SomeInterface
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              type SomeObject implements SomeInterface {
                foo: String
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('extends objects by including new types') */
    public function testExtendsObjectsByIncludingNewTypes(): void
    {
        $schema = BuildSchema::build('
            type Query {
              someObject: SomeObject
            }

            type SomeObject {
              oldField: String
            }
        ');
        $newTypesSDL = <<<GRAPHQL
            enum NewEnum {
              VALUE
            }

            interface NewInterface {
              baz: String
            }

            type NewObject implements NewInterface {
              baz: String
            }

            scalar NewScalar

            union NewUnion = NewObject
            GRAPHQL;
        $extendAST = Parser::parse("
            {$newTypesSDL}
            extend type SomeObject {
              newObject: NewObject
              newInterface: NewInterface
              newUnion: NewUnion
              newScalar: NewScalar
              newEnum: NewEnum
              newTree: [SomeObject]!
            }
        ");
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
                type SomeObject {
                  oldField: String
                  newObject: NewObject
                  newInterface: NewInterface
                  newUnion: NewUnion
                  newScalar: NewScalar
                  newEnum: NewEnum
                  newTree: [SomeObject]!
                }

                {$newTypesSDL}
                GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('extends objects by adding implemented new interfaces') */
    public function testExtendsObjectsByAddingImplementedNewInterfaces(): void
    {
        $schema = BuildSchema::build('
            type Query {
              someObject: SomeObject
            }

            type SomeObject implements OldInterface {
              oldField: String
            }

            interface OldInterface {
              oldField: String
            }
        ');
        $extendAST = Parser::parse('
            extend type SomeObject implements NewInterface {
              newField: String
            }

            interface NewInterface {
              newField: String
            }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
                type SomeObject implements OldInterface & NewInterface {
                  oldField: String
                  newField: String
                }

                interface NewInterface {
                  newField: String
                }
                GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('extends different types multiple times') */
    public function testExtendsDifferentTypesMultipleTimes(): void
    {
        $schema = BuildSchema::build('
            type Query {
              someScalar: SomeScalar
              someObject(someInput: SomeInput): SomeObject
              someInterface: SomeInterface
              someEnum: SomeEnum
              someUnion: SomeUnion
            }

            scalar SomeScalar

            type SomeObject implements SomeInterface {
              oldField: String
            }

            interface SomeInterface {
              oldField: String
            }

            enum SomeEnum {
              OLD_VALUE
            }

            union SomeUnion = SomeObject

            input SomeInput {
              oldField: String
            }
        ');
        $newTypesSDL = <<<GRAPHQL
            scalar NewScalar

            scalar AnotherNewScalar

            type NewObject {
              foo: String
            }

            type AnotherNewObject {
              foo: String
            }

            interface NewInterface {
              newField: String
            }

            interface AnotherNewInterface {
              anotherNewField: String
            }
            GRAPHQL;

        $schemaWithNewTypes = SchemaExtender::extend($schema, Parser::parse($newTypesSDL));
        self::assertSame(
            $newTypesSDL,
            self::printSchemaChanges($schema, $schemaWithNewTypes)
        );

        // TODO see https://github.com/webonyx/graphql-php/issues/1140
        // extend scalar SomeScalar @specifiedBy(url: "http://example.com/foo_spec")
        $extendAST = Parser::parse('
            extend type SomeObject implements NewInterface {
              newField: String
            }

            extend type SomeObject implements AnotherNewInterface {
              anotherNewField: String
            }

            extend enum SomeEnum {
              NEW_VALUE
            }

            extend enum SomeEnum {
              ANOTHER_NEW_VALUE
            }

            extend union SomeUnion = NewObject

            extend union SomeUnion = AnotherNewObject

            extend input SomeInput {
              newField: String
            }

            extend input SomeInput {
              anotherNewField: String
            }
        ');
        $extendedSchema = SchemaExtender::extend($schemaWithNewTypes, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            // TODO see https://github.com/webonyx/graphql-php/issues/1140
            // scalar SomeScalar @specifiedBy(url: \"http://example.com/foo_spec\")
            <<<GRAPHQL
                type SomeObject implements SomeInterface & NewInterface & AnotherNewInterface {
                  oldField: String
                  newField: String
                  anotherNewField: String
                }

                enum SomeEnum {
                  OLD_VALUE
                  NEW_VALUE
                  ANOTHER_NEW_VALUE
                }

                union SomeUnion = SomeObject | NewObject | AnotherNewObject

                input SomeInput {
                  oldField: String
                  newField: String
                  anotherNewField: String
                }

                {$newTypesSDL}
                GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('extends interfaces by adding new fields') */
    public function testExtendsInterfacesByAddingNewFields(): void
    {
        $schema = BuildSchema::build('
          interface SomeInterface {
            oldField: String
          }

          interface AnotherInterface implements SomeInterface {
            oldField: String
          }

          type SomeObject implements SomeInterface & AnotherInterface {
            oldField: String
          }

          type Query {
            someInterface: SomeInterface
          }
        ');
        $extendAST = Parser::parse('
          extend interface SomeInterface {
            newField: String
          }

          extend interface AnotherInterface {
            newField: String
          }

          extend type SomeObject {
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              interface SomeInterface {
                oldField: String
                newField: String
              }

              interface AnotherInterface implements SomeInterface {
                oldField: String
                newField: String
              }

              type SomeObject implements SomeInterface & AnotherInterface {
                oldField: String
                newField: String
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('extends interfaces by adding new implemented interfaces') */
    public function testExtendsInterfacesByAddingNewImplementedInterfaces(): void
    {
        $schema = BuildSchema::build('
          interface SomeInterface {
            oldField: String
          }

          interface AnotherInterface implements SomeInterface {
            oldField: String
          }

          type SomeObject implements SomeInterface & AnotherInterface {
            oldField: String
          }

          type Query {
            someInterface: SomeInterface
          }
        ');
        $extendAST = Parser::parse('
          interface NewInterface {
            newField: String
          }

          extend interface AnotherInterface implements NewInterface {
            newField: String
          }

          extend type SomeObject implements NewInterface {
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              interface AnotherInterface implements SomeInterface & NewInterface {
                oldField: String
                newField: String
              }

              type SomeObject implements SomeInterface & AnotherInterface & NewInterface {
                oldField: String
                newField: String
              }

              interface NewInterface {
                newField: String
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('allows extension of interface with missing Object fields') */
    public function testAllowsExtensionOfInterfaceWithMissingObjectFields(): void
    {
        $schema = BuildSchema::build('
          type Query {
            someInterface: SomeInterface
          }

          type SomeObject implements SomeInterface {
            oldField: SomeInterface
          }

          interface SomeInterface {
            oldField: SomeInterface
          }
        ');
        $extendAST = Parser::parse('
          extend interface SomeInterface {
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertGreaterThan(0, $extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              interface SomeInterface {
                oldField: SomeInterface
                newField: String
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('extends interfaces multiple times') */
    public function testExtendsInterfacesMultipleTimes(): void
    {
        $schema = BuildSchema::build('
          type Query {
            someInterface: SomeInterface
          }

          interface SomeInterface {
            some: SomeInterface
          }
        ');
        $extendAST = Parser::parse('
          extend interface SomeInterface {
            newFieldA: Int
          }

          extend interface SomeInterface {
            newFieldB(test: Boolean): String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            <<<GRAPHQL
              interface SomeInterface {
                some: SomeInterface
                newFieldA: Int
                newFieldB(test: Boolean): String
              }
              GRAPHQL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('may extend mutations and subscriptions') */
    public function testMayExtendMutationsAndSubscriptions(): void
    {
        $mutationSchema = BuildSchema::build('
            type Query {
              queryField: String
            }

            type Mutation {
              mutationField: String
            }

            type Subscription {
              subscriptionField: String
            }
        ');

        $ast = Parser::parse('
            extend type Query {
              newQueryField: Int
            }

            extend type Mutation {
              newMutationField: Int
            }

            extend type Subscription {
              newSubscriptionField: Int
            }
        ');

        $originalPrint = SchemaPrinter::doPrint($mutationSchema);
        $extendedSchema = SchemaExtender::extend($mutationSchema, $ast);

        self::assertNotEquals($mutationSchema, $extendedSchema);
        self::assertSame($originalPrint, SchemaPrinter::doPrint($mutationSchema));
        self::assertSame(
            <<<GRAPHQL
                type Query {
                  queryField: String
                  newQueryField: Int
                }

                type Mutation {
                  mutationField: String
                  newMutationField: Int
                }

                type Subscription {
                  subscriptionField: String
                  newSubscriptionField: Int
                }
                
                GRAPHQL,
            SchemaPrinter::doPrint($extendedSchema),
        );
    }

    /** @see it('may extend directives with new directive') */
    public function testMayExtendDirectivesWithNewDirective(): void
    {
        $schema = BuildSchema::build('
            type Query {
              foo: String
            }
        ');
        $extensionSDL = <<<GRAPHQL
            "New directive."
            directive @new(enable: Boolean!, tag: String) repeatable on QUERY | FIELD
            GRAPHQL;
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));

        self::assertEmpty($extendedSchema->validate());
        self::assertSame(
            $extensionSDL,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /** @see it('Rejects invalid SDL') */
    public function testRejectsInvalidSDL(): void
    {
        $schema = new Schema([]);
        $extendAST = Parser::parse('extend schema @unknown');

        $this->expectException(Error::class);
        $this->expectExceptionMessage(KnownDirectives::unknownDirectiveMessage('unknown'));

        SchemaExtender::extend($schema, $extendAST);
    }

    /** @see it('Allows to disable SDL validation') */
    public function testAllowsToDisableSDLValidation(): void
    {
        $schema = new Schema([]);
        $extendAST = Parser::parse('extend schema @unknown');

        self::expectNotToPerformAssertions();

        SchemaExtender::extend($schema, $extendAST, ['assumeValid' => true]);
        SchemaExtender::extend($schema, $extendAST, ['assumeValidSDL' => true]);
    }

    /** @see it('Throws on unknown types') */
    public function testThrowsOnUnknownTypes(): void
    {
        $schema = new Schema([]);
        $ast = Parser::parse('
            type Query {
              unknown: UnknownType
            }
        ');
        // Differing from graphql-js, we do not throw immediately due to lazy type loading
        $extendedSchema = SchemaExtender::extend($schema, $ast, ['assumeValidSDL' => true]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Unknown type: "UnknownType".');
        $extendedSchema->assertValid();
    }

    /** @see it('does not allow replacing a default directive') */
    public function testDoesNotAllowReplacingADefaultDirective(): void
    {
        $schema = new Schema([]);
        $extendAST = Parser::parse('
            directive @include(if: Boolean!) on FIELD | FRAGMENT_SPREAD
        ');

        $this->expectException(Error::class);
        $this->expectExceptionMessage(
            'Directive "@include" already exists in the schema. It cannot be redefined.',
        );
        SchemaExtender::extend($schema, $extendAST);
    }

    /** @see it('does not allow replacing an existing enum value') */
    public function testDoesNotAllowReplacingAnExistingEnumValue(): void
    {
        $schema = BuildSchema::build('
            enum SomeEnum {
              ONE
            }
        ');
        $extendAST = Parser::parse('
            extend enum SomeEnum {
              ONE
            }
        ');

        $this->expectException(Error::class);
        $this->expectExceptionMessage(
            'Enum value "SomeEnum.ONE" already exists in the schema. It cannot also be defined in this type extension.',
        );
        SchemaExtender::extend($schema, $extendAST);
    }

    /**
     * @see describe('can add additional root operation types')
     * @see it('does not automatically include common root type names')
     */
    public function testDoesNotAutomaticallyIncludeCommonRootTypeNames(): void
    {
        $schema = new Schema([]);
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('type Mutation'));

        self::assertNotNull($extendedSchema->getType('Mutation'));
        self::assertNull($extendedSchema->getMutationType());
    }

    /** @see it('adds schema definition missing in the original schema') */
    public function testAddsSchemaDefinitionMissingInTheOriginalSchema(): void
    {
        $schema = BuildSchema::build('
            directive @foo on SCHEMA
            type Foo
        ');

        self::assertNull($schema->getQueryType());

        $extensionSDL = <<<GRAPHQL
            schema @foo {
              query: Foo
            }
            GRAPHQL;

        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));
        $queryType = $extendedSchema->getQueryType();

        assert($queryType instanceof ObjectType);
        self::assertSame('Foo', $queryType->name);
        self::assertSame($extensionSDL, $this->printASTSchema($extendedSchema));
    }

    /** @see it('adds new root types via schema extension') */
    public function testAddsNewRootTypesViaSchemaExtension(): void
    {
        $schema = BuildSchema::build('
            type Query
            type MutationRoot
        ');
        $extensionSDL = <<<GRAPHQL
            extend schema {
              mutation: MutationRoot
            }
            
            GRAPHQL;
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));

        $mutationType = $extendedSchema->getMutationType();
        assert($mutationType instanceof ObjectType);
        self::assertSame('MutationRoot', $mutationType->name);
        self::assertSame(
            $extensionSDL,
            $this->printExtensionNodes($extendedSchema)
        );
    }

    /** @see it('adds directive via schema extension') */
    public function testAddsDirectiveViaSchemaExtension(): void
    {
        $schema = BuildSchema::build('
            type Query

            directive @foo on SCHEMA
        ');
        $extensionSDL = <<<GRAPHQL
            extend schema @foo
            
            GRAPHQL;
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));

        self::assertSame(
            $extensionSDL,
            $this->printExtensionNodes($extendedSchema)
        );
    }

    /** @see it('adds multiple new root types via schema extension') */
    public function testAddsMultipleNewRootTypesViaSchemaExtension(): void
    {
        $schema = BuildSchema::build('type Query');
        $extendAST = Parser::parse('
            extend schema {
              mutation: Mutation
              subscription: Subscription
            }

            type Mutation
            type Subscription
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        $mutationType = $extendedSchema->getMutationType();
        assert($mutationType instanceof ObjectType);
        self::assertSame('Mutation', $mutationType->name);

        $subscriptionType = $extendedSchema->getSubscriptionType();
        assert($subscriptionType instanceof ObjectType);
        self::assertSame('Subscription', $subscriptionType->name);
    }

    /** @see it('applies multiple schema extensions') */
    public function testAppliesMultipleSchemaExtensions(): void
    {
        $schema = BuildSchema::build('type Query');
        $extendAST = Parser::parse('
            extend schema {
              mutation: Mutation
            }
            type Mutation

            extend schema {
              subscription: Subscription
            }
            type Subscription
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        $mutationType = $extendedSchema->getMutationType();
        assert($mutationType instanceof ObjectType);
        self::assertSame('Mutation', $mutationType->name);

        $subscriptionType = $extendedSchema->getSubscriptionType();
        assert($subscriptionType instanceof ObjectType);
        self::assertSame('Subscription', $subscriptionType->name);
    }

    /** @see it('schema extension AST are available from schema object') */
    public function testSchemaExtensionASTAreAvailableFromSchemaObject(): void
    {
        $schema = BuildSchema::build('
            type Query

            directive @foo on SCHEMA
        ');
        $extendAST = Parser::parse('
            extend schema {
              mutation: Mutation
            }
            type Mutation

            extend schema {
              subscription: Subscription
            }
            type Subscription
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        $secondExtendAST = Parser::parse('extend schema @foo');
        $extendedTwiceSchema = SchemaExtender::extend($extendedSchema, $secondExtendAST);

        self::assertSame(
            <<<GRAPHQL
                extend schema {
                  mutation: Mutation
                }

                extend schema {
                  subscription: Subscription
                }

                extend schema @foo
                
                GRAPHQL,
            $this->printExtensionNodes($extendedTwiceSchema)
        );
    }

    /** @see https://github.com/webonyx/graphql-php/pull/381 */
    public function testOriginalResolversArePreserved(): void
    {
        $value = 'Hello World!';

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => $value,
                ],
            ],
        ]);

        $schema = new Schema(['query' => $queryType]);

        $documentNode = Parser::parse(/** @lang GraphQL */ '
            extend type Query {
                misc: String
            }
        ');

        $extendedSchema = SchemaExtender::extend($schema, $documentNode);
        $extendedQueryType = $extendedSchema->getQueryType();
        assert($extendedQueryType instanceof ObjectType);

        $helloResolveFn = $extendedQueryType->getField('hello')->resolveFn;
        self::assertIsCallable($helloResolveFn);

        $query = /** @lang GraphQL */ '{ hello }';
        $result = GraphQL::executeQuery($extendedSchema, $query);
        self::assertSame(['data' => ['hello' => $value]], $result->toArray());
    }

    public function testOriginalResolveFieldIsPreserved(): void
    {
        $value = 'Hello World!';

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::string(),
                ],
            ],
            'resolveField' => static fn (): string => $value,
        ]);

        $schema = new Schema(['query' => $queryType]);

        $documentNode = Parser::parse(/** @lang GraphQL */ '
            extend type Query {
                misc: String
            }
        ');

        $extendedSchema = SchemaExtender::extend($schema, $documentNode);
        $extendedQueryType = $extendedSchema->getQueryType();
        assert($extendedQueryType instanceof ObjectType);

        $queryResolveFieldFn = $extendedQueryType->resolveFieldFn;
        self::assertIsCallable($queryResolveFieldFn);

        $query = /** @lang GraphQL */ '{ hello }';
        $result = GraphQL::executeQuery($extendedSchema, $query);
        self::assertSame(['data' => ['hello' => $value]], $result->toArray());
    }

    /** @see https://github.com/webonyx/graphql-php/issues/180 */
    public function testShouldBeAbleToIntroduceNewTypesThroughExtension(): void
    {
        $sdl = /** @lang GraphQL */ '
          type Query {
            defaultValue: String
          }

          type Foo {
            value: Int
          }
        ';

        $documentNode = Parser::parse($sdl);
        $schema = BuildSchema::build($documentNode);

        $extensionSdl = /** @lang GraphQL */ '
          type Bar {
            foo: Foo
          }
        ';

        $extendedDocumentNode = Parser::parse($extensionSdl);
        $extendedSchema = SchemaExtender::extend($schema, $extendedDocumentNode);

        static::assertSame(
            <<<GRAPHQL
            type Query {
              defaultValue: String
            }

            type Foo {
              value: Int
            }

            type Bar {
              foo: Foo
            }
            
            GRAPHQL,
            SchemaPrinter::doPrint($extendedSchema)
        );
    }

    /** @see https://github.com/webonyx/graphql-php/pull/929 */
    public function testPreservesRepeatableInDirective(): void
    {
        $schema = BuildSchema::build(/** @lang GraphQL */ '
            directive @test(arg: Int) repeatable on FIELD | SCALAR
        ');

        $directive = $schema->getDirective('test');
        assert($directive instanceof Directive);

        self::assertTrue($directive->isRepeatable);

        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('scalar Foo'));

        $extendedDirective = $extendedSchema->getDirective('test');
        assert($extendedDirective instanceof Directive);

        self::assertTrue($extendedDirective->isRepeatable);
    }

    public function testSupportsTypeConfigDecorator(): void
    {
        $helloValue = 'Hello World!';

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::string(),
                ],
            ],
            'resolveField' => static fn (): string => $helloValue,
        ]);

        $schema = new Schema(['query' => $queryType]);

        $documentNode = Parser::parse(/** @lang GraphQL */ '
              type Foo {
                value: String
              }

              extend type Query {
                fieldDecorated: String
                foo: Foo
              }
        ');

        $fooValue = 'bar';
        $typeConfigDecorator = static function ($typeConfig) use ($fooValue) {
            if ($typeConfig['name'] === 'Foo') {
                $typeConfig['resolveField'] = static fn (): string => $fooValue;
            }

            return $typeConfig;
        };

        $resolveFn = static fn (): string => 'coming from field decorated resolver';
        $fieldConfigDecorator = static function (array $typeConfig, FieldDefinitionNode $fieldDefinitionNode) use ($resolveFn) {
            /** @var UnnamedFieldDefinitionConfig $typeConfig */
            if ($fieldDefinitionNode->name->value === 'fieldDecorated') {
                $typeConfig['resolve'] = $resolveFn;
            }

            return $typeConfig;
        };

        $extendedSchema = SchemaExtender::extend($schema, $documentNode, [], $typeConfigDecorator, $fieldConfigDecorator);

        $query = /** @lang GraphQL */ '
        {
            hello
            foo {
              value
            }
            fieldDecorated
        }
        ';
        $result = GraphQL::executeQuery($extendedSchema, $query);

        self::assertSame([
            'data' => [
                'hello' => $helloValue,
                'foo' => [
                    'value' => $fooValue,
                ],
                'fieldDecorated' => 'coming from field decorated resolver',
            ],
        ], $result->toArray());
    }

    public function testPreservesScalarClassMethods(): void
    {
        $SomeScalarClassType = new SomeScalarClassType();

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'someScalarClass' => ['type' => $SomeScalarClassType],
            ],
        ]);

        $schema = new Schema(['query' => $queryType]);

        $documentNode = Parser::parse(/** @lang GraphQL */ '
        extend type Query {
            foo: ID
        }
        ');

        $extendedSchema = SchemaExtender::extend($schema, $documentNode);
        $extendedScalar = $extendedSchema->getType('SomeScalarClass');

        self::assertInstanceOf(CustomScalarType::class, $extendedScalar);
        self::assertSame(SomeScalarClassType::SERIALIZE_RETURN, $extendedScalar->serialize(null));
        self::assertSame(SomeScalarClassType::PARSE_VALUE_RETURN, $extendedScalar->parseValue(null));
        self::assertSame(SomeScalarClassType::PARSE_LITERAL_RETURN, $extendedScalar->parseLiteral(new IntValueNode(['value' => '1'])));
    }

    public function testPreservesResolveTypeMethod(): void
    {
        $SomeInterfaceClassType = new SomeInterfaceClassType([
            'name' => 'SomeInterface',
            'fields' => [
                'foo' => ['type' => Type::string()],
            ],
        ]);

        $FooType = new ObjectType([
            'name' => 'Foo',
            'interfaces' => [$SomeInterfaceClassType],
            'fields' => [
                'foo' => ['type' => Type::string()],
            ],
        ]);

        $BarType = new ObjectType([
            'name' => 'Bar',
            'fields' => [
                'bar' => ['type' => Type::string()],
            ],
        ]);

        $SomeUnionClassType = new SomeUnionClassType([
            'name' => 'SomeUnion',
            'types' => [$FooType, $BarType],
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'someUnion' => ['type' => $SomeUnionClassType],
                'someInterface' => ['type' => $SomeInterfaceClassType],
            ],
            'resolveField' => static fn (): \stdClass => new \stdClass(),
        ]);

        $schema = new Schema(['query' => $QueryType]);

        $documentNode = Parser::parse(/** @lang GraphQL */ '
        extend type Query {
            foo: ID
        }
        ');

        $extendedSchema = SchemaExtender::extend($schema, $documentNode);

        $ExtendedFooType = $extendedSchema->getType('Foo');
        self::assertInstanceOf(ObjectType::class, $ExtendedFooType);

        $SomeInterfaceClassType->concrete = $ExtendedFooType;
        $SomeUnionClassType->concrete = $ExtendedFooType;

        $query = /** @lang GraphQL */ '
        {
            someUnion {
                __typename
            }
            someInterface {
                __typename
            }
        }
        ';
        $result = GraphQL::executeQuery($extendedSchema, $query);

        self::assertSame([
            'data' => [
                'someUnion' => ['__typename' => 'Foo'],
                'someInterface' => ['__typename' => 'Foo'],
            ],
        ], $result->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS));
    }

    public function testPreservesIsTypeOfMethod(): void
    {
        $SomeInterfaceType = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => [
                'foo' => ['type' => Type::string()],
            ],
        ]);

        $FooClassType = new SomeObjectClassType([
            'name' => 'Foo',
            'interfaces' => [$SomeInterfaceType],
            'fields' => [
                'foo' => ['type' => Type::string()],
            ],
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'someInterface' => ['type' => $SomeInterfaceType],
            ],
            'resolveField' => static fn (): \stdClass => new \stdClass(),
        ]);

        $schema = new Schema([
            'query' => $QueryType,
            'types' => [$FooClassType],
        ]);

        $documentNode = Parser::parse(/** @lang GraphQL */ '
        extend type Query {
            foo: ID
        }
        ');

        $extendedSchema = SchemaExtender::extend($schema, $documentNode);

        $query = /** @lang GraphQL */ '
        {
            someInterface {
                __typename
            }
        }
        ';
        $result = GraphQL::executeQuery($extendedSchema, $query);

        self::assertSame([
            'data' => [
                'someInterface' => ['__typename' => 'Foo'],
            ],
        ], $result->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS));
    }
}
