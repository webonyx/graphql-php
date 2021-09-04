<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
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
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaExtender;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function in_array;
use function iterator_to_array;
use function preg_match;
use function preg_replace;
use function property_exists;
use function trim;

class SchemaExtenderTest extends TestCase
{
    protected function dedent(string $str): string
    {
        $trimmedStr = trim($str, "\n");
        $trimmedStr = preg_replace('/[ \t]*$/', '', $trimmedStr);

        preg_match('/^[ \t]*/', $trimmedStr, $indentMatch);
        $indent = $indentMatch[0];

        return preg_replace('/^' . $indent . '/m', '', $trimmedStr);
    }

    private function printExtensionNodes($obj): string
    {
        Utils::invariant($obj !== null && property_exists($obj, 'extensionASTNodes') && $obj->extensionASTNodes !== null);

        return Printer::doPrint(new DocumentNode([
            'definitions' => new NodeList($obj->extensionASTNodes),
        ]));
    }

    protected function printSchemaChanges(Schema $schema, Schema $extendedSchema): string
    {
        $schemaDefinitions = array_map(
            static fn ($node) => Printer::doPrint($node),
            iterator_to_array(Parser::parse(SchemaPrinter::doPrint($schema))->definitions->getIterator())
        );

        $ast = Parser::parse(SchemaPrinter::doPrint($extendedSchema));

        $extraDefinitions = array_values(array_filter(
            iterator_to_array($ast->definitions->getIterator()),
            static fn (Node $node): bool => ! in_array(Printer::doPrint($node), $schemaDefinitions, true)
        ));

        return Printer::doPrint(new DocumentNode([
            'definitions' => new NodeList($extraDefinitions),
        ]));
    }

    private function printASTNode($obj): string
    {
        Utils::invariant($obj !== null && property_exists($obj, 'astNode') && $obj->astNode !== null);

        return Printer::doPrint($obj->astNode);
    }

    /**
     * graphql-js uses printASTNode() everywhere, but our Schema doesn't have astNode property,
     * hence this helper method that calls getAstNode() instead
     */
    private function printASTSchema(Schema $schema): string
    {
        Utils::invariant($schema->getAstNode() !== null);

        return Printer::doPrint($schema->getAstNode());
    }

    /**
     * @see it('returns the original schema when there are no type definitions')
     */
    public function testReturnsTheOriginalSchemaWhenThereAreNoTypeDefinitions(): void
    {
        $schema         = BuildSchema::build('type Query');
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('{ field }'));
        self::assertEquals($schema, $extendedSchema);
    }

    /**
     * @see it('can be used for limited execution')
     */
    public function testCanBeUsedForLimitedExecution(): void
    {
        $schema         = BuildSchema::build('type Query');
        $extendAST      = Parser::parse('
          extend type Query {
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        $result = GraphQL::executeQuery($extendedSchema, '{ newField }', ['newField' => 123]);

        self::assertEquals(
            ['data' => ['newField' => '123']],
            $result->toArray(),
        );
    }

    /**
     * @see it('extends objects by adding new fields')
     */
    public function testExtendsObjectsByAddingNewFields(): void
    {
        $schema         = BuildSchema::build('
          type Query {
            someObject: SomeObject
          }

          type SomeObject implements AnotherInterface & SomeInterface {
            self: SomeObject
            tree: [SomeObject]!
            """Old field description."""
            oldField: String
          }

          interface SomeInterface {
            self: SomeInterface
          }

          interface AnotherInterface {
            self: SomeObject
          }
        ');
        $extensionSDL   = '
          extend type SomeObject {
            """New field description."""
            newField(arg: Boolean): String
          }
        ';
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              type SomeObject implements AnotherInterface & SomeInterface {
                self: SomeObject
                tree: [SomeObject]!
                """Old field description."""
                oldField: String
                """New field description."""
                newField(arg: Boolean): String
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema),
        );
    }

    /**
     * @see it('can describe the extended fields with legacy comments')
     */
    public function testCanDescribeTheExtendedFieldsWithLegacyComments(): void
    {
        $schema         = BuildSchema::build('type Query');
        $extendAST      = Parser::parse('
          extend type Query {
            # New field description.
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST, ['commentDescriptions' => true]);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              type Query {
                """New field description."""
                newField: String
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema),
        );
    }

    /**
     * @see it('describes extended fields with strings when present')
     */
    public function testDescribesExtendedFieldsWithStringsWhenPresent(): void
    {
        $schema         = BuildSchema::build('type Query');
        $extendAST      = Parser::parse('
          extend type Query {
            # New field description.
            "Actually use this description."
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST, ['commentDescriptions' => true]);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              type Query {
                """Actually use this description."""
                newField: String
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema),
        );
    }

    /**
     * @see it('ignores comment description on extended fields if location is not provided')
     */
    public function testIgnoresCommentDescriptionOnExtendedFieldsIfLocationIsNotProvided(): void
    {
        $schema         = BuildSchema::build('type Query');
        $extendSDL      = '
          extend type Query {
            # New field description.
            newField: String
          }
        ';
        $extendAST      = Parser::parse($extendSDL, ['noLocation' => true]);
        $extendedSchema = SchemaExtender::extend($schema, $extendAST, ['commentDescriptions' => true]);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              type Query {
                newField: String
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema),
        );
    }

    /**
     * @see it('extends objects with standard type fields')
     */
    public function testExtendsObjectsWithStandardTypeFields(): void
    {
        $schema = BuildSchema::build('type Query');
        // String and Boolean are always included through introspection types
        self::assertNull($schema->getType('Int'));
        self::assertNull($schema->getType('Float'));
        self::assertSame(Type::string(), $schema->getType('String'));
        self::assertSame(Type::boolean(), $schema->getType('Boolean'));
        self::assertNull($schema->getType('ID'));

        $extendAST      = Parser::parse('
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

        $extendTwiceAST      = Parser::parse('
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

    /**
     * @see it('extends enums by adding new values')
     */
    public function testExtendsEnumsByAddingNewValues(): void
    {
        $schema         = BuildSchema::build('
          type Query {
            someEnum(arg: SomeEnum): SomeEnum
          }

          directive @foo(arg: SomeEnum) on SCHEMA

          enum SomeEnum {
            """Old value description."""
            OLD_VALUE
          }
        ');
        $extendAST      = Parser::parse('
          extend enum SomeEnum {
            """New value description."""
            NEW_VALUE
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              enum SomeEnum {
                """Old value description."""
                OLD_VALUE
                """New value description."""
                NEW_VALUE
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema),
        );
    }

    /**
     * @see it('extends unions by adding new types')
     */
    public function testExtendsUnionsByAddingNewTypes(): void
    {
        $schema         = BuildSchema::build('
          type Query {
            someUnion: SomeUnion
          }

          union SomeUnion = Foo | Biz

          type Foo { foo: String }
          type Biz { biz: String }
          type Bar { bar: String }
        ');
        $extendAST      = Parser::parse('
          extend union SomeUnion = Bar
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              union SomeUnion = Foo | Biz | Bar
            '),
            $this->printSchemaChanges($schema, $extendedSchema),
        );
    }

    /**
     * @see it('allows extension of union by adding itself')
     */
    public function testAllowsExtensionOfUnionByAddingItself(): void
    {
        $schema         = BuildSchema::build('
          union SomeUnion
        ');
        $extendAST      = Parser::parse('
          extend union SomeUnion = SomeUnion
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertGreaterThan(0, count($extendedSchema->validate()));
        self::assertEquals(
            $this->dedent('
                union SomeUnion = SomeUnion
            '),
            $this->printSchemaChanges($schema, $extendedSchema),
        );
    }

    /**
     * @see it('extends inputs by adding new fields')
     */
    public function testExtendsInputsByAddingNewFields(): void
    {
        $schema         = BuildSchema::build('
          type Query {
            someInput(arg: SomeInput): String
          }

          directive @foo(arg: SomeInput) on SCHEMA

          input SomeInput {
            """Old field description."""
            oldField: String
          }
        ');
        $extendAST      = Parser::parse('
          extend input SomeInput {
            """New field description."""
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              input SomeInput {
                """Old field description."""
                oldField: String
                """New field description."""
                newField: String
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema),
        );
    }

    /**
     * @see it('extends scalars by adding new directives')
     */
    public function testExtendsScalarsByAddingNewDirectives(): void
    {
        $schema         = BuildSchema::build('
          type Query {
            someScalar(arg: SomeScalar): SomeScalar
          }

          directive @foo(arg: SomeScalar) on SCALAR

          input FooInput {
            foo: SomeScalar
          }

          scalar SomeScalar
        ');
        $extensionSDL   = $this->dedent('
          extend scalar SomeScalar @foo
        ');
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));
        $someScalar     = $extendedSchema->getType('SomeScalar');

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $extensionSDL,
            $this->printExtensionNodes($someScalar),
        );
    }

    /**
     * @see it('correctly assign AST nodes to new and extended types')
     */
    public function testCorrectlyAssignASTNodesToNewAndExtendedTypes(): void
    {
        $schema            = BuildSchema::build('
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

          enum TestEnum {
            TEST_VALUE
          }

          input TestInput {
            testInputField: TestEnum
          }
        ');
        $extendedSchema    = SchemaExtender::extend($schema, $firstExtensionAST);

        $secondExtensionAST  = Parser::parse('
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

        self::assertEquals(
            SchemaPrinter::doPrint($extendedInOneGoSchema),
            SchemaPrinter::doPrint($extendedTwiceSchema),
        );

        $query = $extendedTwiceSchema->getQueryType();
        /** @var EnumType $someEnum */
        $someEnum = $extendedTwiceSchema->getType('SomeEnum');
        /** @var UnionType $someUnion */
        $someUnion = $extendedTwiceSchema->getType('SomeUnion');
        /** @var ScalarType $someScalar */
        $someScalar = $extendedTwiceSchema->getType('SomeScalar');
        /** @var InputObjectType $someInput */
        $someInput = $extendedTwiceSchema->getType('SomeInput');
        /** @var InterfaceType $someInterface */
        $someInterface = $extendedTwiceSchema->getType('SomeInterface');

        /** @var InputObjectType $testInput */
        $testInput = $extendedTwiceSchema->getType('TestInput');
        /** @var EnumType $testEnum */
        $testEnum = $extendedTwiceSchema->getType('TestEnum');
        /** @var UnionType $testUnion */
        $testUnion = $extendedTwiceSchema->getType('TestUnion');
        /** @var ObjectType $testType */
        $testType = $extendedTwiceSchema->getType('TestType');
        /** @var InterfaceType $testInterface */
        $testInterface = $extendedTwiceSchema->getType('TestInterface');
        /** @var Directive $testDirective */
        $testDirective = $extendedTwiceSchema->getDirective('test');

        self::assertCount(0, $testType->extensionASTNodes);
        self::assertCount(0, $testEnum->extensionASTNodes);
        self::assertCount(0, $testUnion->extensionASTNodes);
        self::assertCount(0, $testInput->extensionASTNodes);
        self::assertCount(0, $testInterface->extensionASTNodes);

        self::assertCount(2, $query->extensionASTNodes);
        self::assertCount(2, $someScalar->extensionASTNodes);
        self::assertCount(2, $someEnum->extensionASTNodes);
        self::assertCount(2, $someUnion->extensionASTNodes);
        self::assertCount(2, $someInput->extensionASTNodes);
        self::assertCount(2, $someInterface->extensionASTNodes);

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

        // What would be PHPUnit's equivalent to expect().to.have.members()?
        self::assertEquals(
            SchemaPrinter::doPrint(SchemaExtender::extend($schema, $restoredExtensionAST)),
            SchemaPrinter::doPrint($extendedTwiceSchema)
        );

        $newField = $query->getField('newField');

        self::assertEquals('newField(testArg: TestInput): TestEnum', $this->printASTNode($newField));
        self::assertEquals('testArg: TestInput', $this->printASTNode($newField->args[0]));
        self::assertEquals('oneMoreNewField: TestUnion', $this->printASTNode($query->getField('oneMoreNewField')));
        self::assertEquals('NEW_VALUE', $this->printASTNode($someEnum->getValue('NEW_VALUE')));
        self::assertEquals('ONE_MORE_NEW_VALUE', $this->printASTNode($someEnum->getValue('ONE_MORE_NEW_VALUE')));
        self::assertEquals('newField: String', $this->printASTNode($someInput->getField('newField')));
        self::assertEquals('oneMoreNewField: String', $this->printASTNode($someInput->getField('oneMoreNewField')));
        self::assertEquals('newField: String', $this->printASTNode($someInterface->getField('newField')));
        self::assertEquals('oneMoreNewField: String', $this->printASTNode($someInterface->getField('oneMoreNewField')));
        self::assertEquals('testInputField: TestEnum', $this->printASTNode($testInput->getField('testInputField')));
        self::assertEquals('TEST_VALUE', $this->printASTNode($testEnum->getValue('TEST_VALUE')));
        self::assertEquals('interfaceField: String', $this->printASTNode($testInterface->getField('interfaceField')));
        self::assertEquals('interfaceField: String', $this->printASTNode($testType->getField('interfaceField')));
        self::assertEquals('arg: Int', $this->printASTNode($testDirective->args[0]));
    }

    /**
     * @see it('builds types with deprecated fields/values')
     */
    public function testBuildsTypesWithDeprecatedFieldsOrValues(): void
    {
        $schema         = new Schema([]);
        $extendAST      = Parser::parse('
          type SomeObject {
            deprecatedField: String @deprecated(reason: "not used anymore")
          }

          enum SomeEnum {
            DEPRECATED_VALUE @deprecated(reason: "do not use")
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        /** @var ObjectType $someType */
        $someType           = $extendedSchema->getType('SomeObject');
        $deprecatedFieldDef = $someType->getField('deprecatedField');

        self::assertEquals(true, $deprecatedFieldDef->isDeprecated());
        self::assertEquals('not used anymore', $deprecatedFieldDef->deprecationReason);

        /** @var EnumType $someEnum */
        $someEnum          = $extendedSchema->getType('SomeEnum');
        $deprecatedEnumDef = $someEnum->getValue('DEPRECATED_VALUE');

        self::assertEquals(true, $deprecatedEnumDef->isDeprecated());
        self::assertEquals('do not use', $deprecatedEnumDef->deprecationReason);
    }

    /**
     * @see it('extends objects with deprecated fields')
     */
    public function testExtendsObjectsWithDeprecatedFields(): void
    {
        $schema         = BuildSchema::build('type SomeObject');
        $extendAST      = Parser::parse('
          extend type SomeObject {
            deprecatedField: String @deprecated(reason: "not used anymore")
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        /** @var ObjectType $someType */
        $someType           = $extendedSchema->getType('SomeObject');
        $deprecatedFieldDef = $someType->getField('deprecatedField');

        self::assertTrue($deprecatedFieldDef->isDeprecated());
        self::assertEquals('not used anymore', $deprecatedFieldDef->deprecationReason);
    }

    /**
     * @see it('extends enums with deprecated values')
     */
    public function testExtendsEnumsWithDeprecatedValues(): void
    {
        $schema         = BuildSchema::build('enum SomeEnum');
        $extendAST      = Parser::parse('
          extend enum SomeEnum {
            DEPRECATED_VALUE @deprecated(reason: "do not use")
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        /** @var EnumType $someEnum */
        $someEnum          = $extendedSchema->getType('SomeEnum');
        $deprecatedEnumDef = $someEnum->getValue('DEPRECATED_VALUE');

        self::assertTrue($deprecatedEnumDef->isDeprecated());
        self::assertEquals('do not use', $deprecatedEnumDef->deprecationReason);
    }

    /**
     * @see it('adds new unused types')
     */
    public function testAddsNewUnusedTypes(): void
    {
        $schema         = BuildSchema::build('
            type Query {
              dummy: String
            }
        ');
        $extensionSDL   = $this->dedent('
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
        ');
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $extensionSDL,
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('extends objects by adding new fields with arguments')
     */
    public function testExtendsObjectsByAddingNewFieldsWithArguments(): void
    {
        $schema         = BuildSchema::build('
          type SomeObject

          type Query {
            someObject: SomeObject
          }
        ');
        $extendAST      = Parser::parse('
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
        self::assertEquals(
            $this->dedent('
              type SomeObject {
                newField(arg1: String, arg2: NewInputObj!): String
              }

              input NewInputObj {
                field1: Int
                field2: [Float]
                field3: String!
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('extends objects by adding new fields with existing types')
     */
    public function testExtendsObjectsByAddingNewFieldsWithExistingTypes(): void
    {
        $schema         = BuildSchema::build('
          type Query {
            someObject: SomeObject
          }

          type SomeObject
          enum SomeEnum { VALUE }
        ');
        $extendAST      = Parser::parse('
          extend type SomeObject {
            newField(arg1: SomeEnum!): SomeEnum
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              type SomeObject {
                newField(arg1: SomeEnum!): SomeEnum
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('extends objects by adding implemented interfaces')
     */
    public function testExtendsObjectsByAddingImplementedInterfaces(): void
    {
        $schema         = BuildSchema::build('
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
        $extendAST      = Parser::parse('
          extend type SomeObject implements SomeInterface
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              type SomeObject implements SomeInterface {
                foo: String
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('extends objects by including new types')
     */
    public function testExtendsObjectsByIncludingNewTypes(): void
    {
        $schema         = BuildSchema::build('
            type Query {
              someObject: SomeObject
            }

            type SomeObject {
              oldField: String
            }
        ');
        $newTypesSDL    = $this->dedent('
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

            union NewUnion = NewObject');
        $extendAST      = Parser::parse("
            ${newTypesSDL}
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
        self::assertEquals(
            $this->dedent("
                type SomeObject {
                  oldField: String
                  newObject: NewObject
                  newInterface: NewInterface
                  newUnion: NewUnion
                  newScalar: NewScalar
                  newEnum: NewEnum
                  newTree: [SomeObject]!
                }

                ${newTypesSDL}
            "),
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('extends objects by adding implemented new interfaces')
     */
    public function testExtendsObjectsByAddingImplementedNewInterfaces(): void
    {
        $schema         = BuildSchema::build('
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
        $extendAST      = Parser::parse('
            extend type SomeObject implements NewInterface {
              newField: String
            }

            interface NewInterface {
              newField: String
            }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
                type SomeObject implements OldInterface & NewInterface {
                  oldField: String
                  newField: String
                }

                interface NewInterface {
                  newField: String
                }
            '),
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('extends different types multiple times')
     */
    public function testExtendsDifferentTypesMultipleTimes(): void
    {
        $schema         = BuildSchema::build('
            type Query {
              someObject(someInput: SomeInput): SomeObject
              someInterface: SomeInterface
              someEnum: SomeEnum
              someUnion: SomeUnion
            }

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
        $newTypesSDL    = $this->dedent('
            interface AnotherNewInterface {
              anotherNewField: String
            }

            type AnotherNewObject {
              foo: String
            }

            interface NewInterface {
              newField: String
            }

            type NewObject {
              foo: String
            }
        ');
        $extendAST      = Parser::parse("
            ${newTypesSDL}
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
        ");
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent("
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

                ${newTypesSDL}
            "),
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('extends interfaces by adding new fields')
     */
    public function testExtendsInterfacesByAddingNewFields(): void
    {
        $schema         = BuildSchema::build('
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
        $extendAST      = Parser::parse('
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
        self::assertEquals(
            $this->dedent('
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
            '),
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('extends interfaces by adding new implemented interfaces')
     */
    public function testExtendsInterfacesByAddingNewImplementedInterfaces(): void
    {
        $schema         = BuildSchema::build('
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
        $extendAST      = Parser::parse('
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
        self::assertEquals(
            $this->dedent('
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
            '),
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('allows extension of interface with missing Object fields')
     */
    public function testAllowsExtensionOfInterfaceWithMissingObjectFields(): void
    {
        $schema         = BuildSchema::build('
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
        $extendAST      = Parser::parse('
          extend interface SomeInterface {
            newField: String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertGreaterThan(0, $extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              interface SomeInterface {
                oldField: SomeInterface
                newField: String
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('extends interfaces multiple times')
     */
    public function testExtendsInterfacesMultipleTimes(): void
    {
        $schema         = BuildSchema::build('
          type Query {
            someInterface: SomeInterface
          }

          interface SomeInterface {
            some: SomeInterface
          }
        ');
        $extendAST      = Parser::parse('
          extend interface SomeInterface {
            newFieldA: Int
          }

          extend interface SomeInterface {
            newFieldB(test: Boolean): String
          }
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $this->dedent('
              interface SomeInterface {
                some: SomeInterface
                newFieldA: Int
                newFieldB(test: Boolean): String
              }
            '),
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('may extend mutations and subscriptions')
     */
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

        $originalPrint  = SchemaPrinter::doPrint($mutationSchema);
        $extendedSchema = SchemaExtender::extend($mutationSchema, $ast);

        self::assertNotEquals($mutationSchema, $extendedSchema);
        self::assertEquals($originalPrint, SchemaPrinter::doPrint($mutationSchema));
        self::assertEquals(
            $this->dedent('
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
            '),
            SchemaPrinter::doPrint($extendedSchema),
        );
    }

    /**
     * @see it('may extend directives with new directive')
     */
    public function testMayExtendDirectivesWithNewDirective(): void
    {
        $schema         = BuildSchema::build('
            type Query {
              foo: String
            }
        ');
        $extensionSDL   = $this->dedent('
            """New directive."""
            directive @new(enable: Boolean!, tag: String) repeatable on QUERY | FIELD
        ');
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));

        self::assertEmpty($extendedSchema->validate());
        self::assertEquals(
            $extensionSDL,
            $this->printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('sets correct description using legacy comments')
     */
    public function testSetsCorrectDescriptionUsingLegacyComments(): void
    {
        $schema         = BuildSchema::build('
            type Query {
              foo: String
            }
        ');
        $extendAST      = Parser::parse('
            # new directive
            directive @new on QUERY
        ');
        $extendedSchema = SchemaExtender::extend(
            $schema,
            $extendAST,
            ['commentDescriptions' => true]
        );

        $newDirective = $extendedSchema->getDirective('new');
        self::assertEquals('new directive', $newDirective->description);
    }

    /**
     * @see it('Rejects invalid SDL')
     */
    public function testRejectsInvalidSDL(): void
    {
        $schema    = new Schema([]);
        $extendAST = Parser::parse('extend schema @unknown');

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Unknown directive "@unknown".');

        SchemaExtender::extend($schema, $extendAST);
    }

    /**
     * @see it('Allows to disable SDL validation')
     */
    public function testAllowsToDisableSDLValidation(): void
    {
        $schema    = new Schema([]);
        $extendAST = Parser::parse('extend schema @unknown');

        // shouldn't throw
        SchemaExtender::extend($schema, $extendAST, ['assumeValid' => true]);
        SchemaExtender::extend($schema, $extendAST, ['assumeValidSDL' => true]);

        self::assertTrue(true);
    }

    /**
     * @see it('Throws on unknown types')
     */
    public function testThrowsOnUnknownTypes(): void
    {
        $schema = new Schema([]);
        $ast    = Parser::parse('
            type Query {
              unknown: UnknownType
            }
        ');
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Unknown type: "UnknownType".');
        SchemaExtender::extend($schema, $ast, ['assumeValidSDL' => true]);
    }

    /**
     * @see it('does not allow replacing a default directive')
     */
    public function testDoesNotAllowReplacingADefaultDirective(): void
    {
        $schema    = new Schema([]);
        $extendAST = Parser::parse('
            directive @include(if: Boolean!) on FIELD | FRAGMENT_SPREAD
        ');

        $this->expectException(Error::class);
        $this->expectExceptionMessage(
            'Directive "@include" already exists in the schema. It cannot be redefined.',
        );
        SchemaExtender::extend($schema, $extendAST);
    }

    /**
     * @see it('does not allow replacing an existing enum value')
     */
    public function testDoesNotAllowReplacingAnExistingEnumValue(): void
    {
        $schema    = BuildSchema::build('
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

    // describe('can add additional root operation types')

    /**
     * @see it('does not automatically include common root type names')
     */
    public function testDoesNotAutomaticallyIncludeCommonRootTypeNames(): void
    {
        $schema         = new Schema([]);
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('type Mutation'));

        self::assertNotNull($extendedSchema->getType('Mutation'));
        self::assertNull($extendedSchema->getMutationType());
    }

    /**
     * @see it('adds schema definition missing in the original schema')
     */
    public function testAddsSchemaDefinitionMissingInTheOriginalSchema(): void
    {
        $schema = BuildSchema::build('
            directive @foo on SCHEMA
            type Foo
        ');

        self::assertNull($schema->getQueryType());

        $extensionSDL = $this->dedent('
            schema @foo {
              query: Foo
            }
        ');

        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));
        $queryType      = $extendedSchema->getQueryType();

        self::assertEquals('Foo', $queryType->name);
        self::assertEquals($extensionSDL, $this->printASTSchema($schema));
    }

    /**
     * @see it('adds new root types via schema extension')
     */
    public function testAddsNewRootTypesViaSchemaExtension(): void
    {
        $schema         = BuildSchema::build('
            type Query
            type MutationRoot
        ');
        $extensionSDL   = $this->dedent('
            extend schema {
              mutation: MutationRoot
            }
        ');
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));

        $mutationType = $extendedSchema->getMutationType();
        self::assertEquals('MutationRoot', $mutationType->name);
        self::assertEquals(
            $extensionSDL,
            $this->printExtensionNodes($extendedSchema)
        );
    }

    /**
     * @see it('adds directive via schema extension')
     */
    public function testAddsDirectiveViaSchemaExtension(): void
    {
        $schema         = BuildSchema::build('
            type Query

            directive @foo on SCHEMA
        ');
        $extensionSDL   = $this->dedent('
            extend schema @foo
        ');
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse($extensionSDL));

        self::assertEquals(
            $extensionSDL,
            $this->printExtensionNodes($extendedSchema)
        );
    }

    /**
     * @see it('adds multiple new root types via schema extension')
     */
    public function testAddsMultipleNewRootTypesViaSchemaExtension(): void
    {
        $schema         = BuildSchema::build('type Query');
        $extendAST      = Parser::parse('
            extend schema {
              mutation: Mutation
              subscription: Subscription
            }

            type Mutation
            type Subscription
        ');
        $extendedSchema = SchemaExtender::extend($schema, $extendAST);

        $mutationType = $extendedSchema->getMutationType();
        self::assertEquals('Mutation', $mutationType->name);

        $subscriptionType = $extendedSchema->getSubscriptionType();
        self::assertEquals('Subscription', $subscriptionType->name);
    }

    /**
     * @see it('applies multiple schema extensions')
     */
    public function testAppliesMultipleSchemaExtensions(): void
    {
        $schema         = BuildSchema::build('type Query');
        $extendAST      = Parser::parse('
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
        self::assertEquals('Mutation', $mutationType->name);

        $subscriptionType = $extendedSchema->getSubscriptionType();
        self::assertEquals('Subscription', $subscriptionType->name);
    }

    /**
     * @see it('schema extension AST are available from schema object')
     */
    public function testSchemaExtensionASTAreAvailableFromSchemaObject(): void
    {
        $schema         = BuildSchema::build('
            type Query

            directive @foo on SCHEMA
        ');
        $extendAST      = Parser::parse('
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

        $secondExtendAST     = Parser::parse('extend schema @foo');
        $extendedTwiceSchema = SchemaExtender::extend($extendedSchema, $secondExtendAST);

        self::assertEquals(
            $this->dedent('
                extend schema {
                  mutation: Mutation
                }

                extend schema {
                  subscription: Subscription
                }

                extend schema @foo
            '),
            $this->printExtensionNodes($extendedTwiceSchema)
        );
    }

    /**
     * @see https://github.com/webonyx/graphql-php/pull/381
     */
    public function testOriginalResolversArePreserved(): void
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::string(),
                    'resolve' => static function (): string {
                        return 'Hello World!';
                    },
                ],
            ],
        ]);

        $schema = new Schema(['query' => $queryType]);

        $documentNode = Parser::parse('
            extend type Query {
                misc: String
            }
        ');

        $extendedSchema = SchemaExtender::extend($schema, $documentNode);
        $helloResolveFn = $extendedSchema->getQueryType()->getField('hello')->resolveFn;

        self::assertIsCallable($helloResolveFn);

        $query  = '{ hello }';
        $result = GraphQL::executeQuery($extendedSchema, $query);
        self::assertSame(['data' => ['hello' => 'Hello World!']], $result->toArray());
    }

    public function testOriginalResolveFieldIsPreserved(): void
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::string(),
                ],
            ],
            'resolveField' => static function (): string {
                return 'Hello World!';
            },
        ]);

        $schema = new Schema(['query' => $queryType]);

        $documentNode = Parser::parse('
            extend type Query {
                misc: String
            }
        ');

        $extendedSchema      = SchemaExtender::extend($schema, $documentNode);
        $queryResolveFieldFn = $extendedSchema->getQueryType()->resolveFieldFn;

        self::assertIsCallable($queryResolveFieldFn);

        $query  = '{ hello }';
        $result = GraphQL::executeQuery($extendedSchema, $query);
        self::assertSame(['data' => ['hello' => 'Hello World!']], $result->toArray());
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/180
     */
    public function testShouldBeAbleToIntroduceNewTypesThroughExtension(): void
    {
        $sdl = '
          type Query {
            defaultValue: String
          }
          type Foo {
            value: Int
          }
        ';

        $documentNode = Parser::parse($sdl);
        $schema       = BuildSchema::build($documentNode);

        $extensionSdl = '
          type Bar {
            foo: Foo
          }
        ';

        $extendedDocumentNode = Parser::parse($extensionSdl);
        $extendedSchema       = SchemaExtender::extend($schema, $extendedDocumentNode);

        $expected = '
            type Bar {
              foo: Foo
            }

            type Foo {
              value: Int
            }

            type Query {
              defaultValue: String
            }
        ';

        static::assertEquals($this->dedent($expected), SchemaPrinter::doPrint($extendedSchema));
    }

    public function testSupportsTypeConfigDecorator(): void
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::string(),
                ],
            ],
            'resolveField' => static function (): string {
                return 'Hello World!';
            },
        ]);

        $schema = new Schema(['query' => $queryType]);

        $documentNode = Parser::parse('
              type Foo {
                value: String
              }
              extend type Query {
                defaultValue: String
                foo: Foo
              }
        ');

        $typeConfigDecorator = static function ($typeConfig) {
            switch ($typeConfig['name']) {
                case 'Foo':
                    $typeConfig['resolveField'] = static function (): string {
                        return 'bar';
                    };
                    break;
            }

            return $typeConfig;
        };

        $extendedSchema = SchemaExtender::extend($schema, $documentNode, [], $typeConfigDecorator);

        $query  = '{ 
            hello
            foo {
              value
            }
        }';
        $result = GraphQL::executeQuery($extendedSchema, $query);

        self::assertSame(['data' => ['hello' => 'Hello World!', 'foo' => ['value' => 'bar']]], $result->toArray());
    }
}
