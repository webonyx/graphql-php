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
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaExtender;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function in_array;
use function iterator_to_array;
use function preg_match;
use function preg_replace;
use function property_exists;
use function trim;

class SchemaExtenderTest extends TestCase
{
    protected Schema $testSchema;

    /** @var array<string> */
    protected array $testSchemaDefinitions;

    public function setUp(): void
    {
        parent::setUp();

        $this->testSchema = BuildSchema::build('
          scalar SomeScalar
        
          interface SomeInterface {
            some: SomeInterface
          }
          
          interface AnotherInterface implements SomeInterface {
            name: String
            some: AnotherInterface
          }
        
          type Foo implements AnotherInterface & SomeInterface {
            name: String
            some: AnotherInterface
            tree: [Foo]!
          }
        
          type Bar implements SomeInterface {
            some: SomeInterface
            foo: Foo
          }
        
          type Biz {
            fizz: String
          }
        
          union SomeUnion = Foo | Biz
        
          enum SomeEnum {
            ONE
            TWO
          }
        
          input SomeInput {
            fooArg: String
          }
        
          directive @foo(input: SomeInput) repeatable on SCHEMA | SCALAR | OBJECT | FIELD_DEFINITION | ARGUMENT_DEFINITION | INTERFACE | UNION | ENUM | ENUM_VALUE | INPUT_OBJECT | INPUT_FIELD_DEFINITION
        
          type Query {
            foo: Foo
            someScalar: SomeScalar
            someUnion: SomeUnion
            someEnum: SomeEnum
            someInterface(id: ID!): SomeInterface
            someInput(input: SomeInput): String
          }
        ');

        $testSchemaAst = Parser::parse(SchemaPrinter::doPrint($this->testSchema));

        $this->testSchemaDefinitions = array_map(static function ($node): string {
            return Printer::doPrint($node);
        }, iterator_to_array($testSchemaAst->definitions->getIterator()));
    }

    protected function dedent(string $str): string
    {
        $trimmedStr = trim($str, "\n");
        $trimmedStr = preg_replace('/[ \t]*$/', '', $trimmedStr);

        preg_match('/^[ \t]*/', $trimmedStr, $indentMatch);
        $indent = $indentMatch[0];

        return preg_replace('/^' . $indent . '/m', '', $trimmedStr);
    }

    /**
     * @param array<string, bool> $options
     */
    protected function extendTestSchema(string $sdl, array $options = []): Schema
    {
        $originalPrint  = SchemaPrinter::doPrint($this->testSchema);
        $ast            = Parser::parse($sdl);
        $extendedSchema = SchemaExtender::extend($this->testSchema, $ast, $options);

        self::assertEquals(SchemaPrinter::doPrint($this->testSchema), $originalPrint);

        return $extendedSchema;
    }

    protected function printTestSchemaChanges(Schema $extendedSchema): string
    {
        $ast = Parser::parse(SchemaPrinter::doPrint($extendedSchema));

        $extraDefinitions = array_values(array_filter(
            iterator_to_array($ast->definitions->getIterator()),
            function (Node $node): bool {
                return ! in_array(Printer::doPrint($node), $this->testSchemaDefinitions, true);
            }
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
        $extendedSchema = $this->extendTestSchema('{ field }');
        self::assertEquals($this->testSchema, $extendedSchema);
    }

    /**
     * @see it('extends without altering original schema')
     */
    public function testExtendsWithoutAlteringOriginalSchema(): void
    {
        $extendedSchema = $this->extendTestSchema('
            extend type Query {
                newField: String
            }');
        self::assertNotEquals($this->testSchema, $extendedSchema);
        self::assertStringContainsString('newField', SchemaPrinter::doPrint($extendedSchema));
        self::assertStringNotContainsString('newField', SchemaPrinter::doPrint($this->testSchema));
    }

    /**
     * @see it('can be used for limited execution')
     */
    public function testCanBeUsedForLimitedExecution(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend type Query {
            newField: String
          }
        ');

        $result = GraphQL::executeQuery($extendedSchema, '{ newField }', ['newField' => 123]);

        self::assertEquals(
            ['data' => ['newField' => '123']],
            $result->toArray(),
        );
    }

    /**
     * @see it('can describe the extended fields')
     */
    public function testCanDescribeTheExtendedFields(): void
    {
        $extendedSchema = $this->extendTestSchema('
            extend type Query {
                "New field description."
                newField: String
            }
        ');

        self::assertEquals(
            'New field description.',
            $extendedSchema->getQueryType()->getField('newField')->description,
        );
    }

    /**
     * @see it('can describe the extended fields with legacy comments')
     */
    public function testCanDescribeTheExtendedFieldsWithLegacyComments(): void
    {
        $extendedSchema = $this->extendTestSchema('
            extend type Query {
                # New field description.
                newField: String
            }
        ', ['commentDescriptions' => true]);

        self::assertEquals(
            'New field description.',
            $extendedSchema->getQueryType()->getField('newField')->description
        );
    }

    /**
     * @see it('describes extended fields with strings when present')
     */
    public function testDescribesExtendedFieldsWithStringsWhenPresent(): void
    {
        $extendedSchema = $this->extendTestSchema('
            extend type Query {
                # New field description.
                "Actually use this description."
                newField: String
            }
        ', ['commentDescriptions' => true]);

        self::assertEquals(
            'Actually use this description.',
            $extendedSchema->getQueryType()->getField('newField')->description
        );
    }

    /**
     * @see it('extends objects by adding new fields')
     */
    public function testExtendsObjectsByAddingNewFields(): void
    {
        $extendedSchema = $this->extendTestSchema('
            extend type Foo {
                newField: String
            }
        ');

        self::assertEquals(
            $this->dedent('
              type Foo implements AnotherInterface & SomeInterface {
                name: String
                some: AnotherInterface
                tree: [Foo]!
                newField: String
              }
            '),
            $this->printTestSchemaChanges($extendedSchema),
        );

        $fooType  = $extendedSchema->getType('Foo');
        $fooField = $extendedSchema->getQueryType()->getField('foo');
        self::assertEquals($fooField->getType(), $fooType);
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
        $extendedSchema = $this->extendTestSchema('
          extend enum SomeEnum {
            NEW_ENUM
          }
        ');

        self::assertEquals(
            $this->dedent('
              enum SomeEnum {
                ONE
                TWO
                NEW_ENUM
              }
          '),
            $this->printTestSchemaChanges($extendedSchema),
        );

        $someEnumType = $extendedSchema->getType('SomeEnum');
        $enumField    = $extendedSchema->getQueryType()->getField('someEnum');
        self::assertEquals($enumField->getType(), $someEnumType);
    }

    /**
     * @see it('extends unions by adding new types')
     */
    public function testExtendsUnionsByAddingNewTypes(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend union SomeUnion = Bar
        ');
        self::assertEquals(
            $this->dedent('
              union SomeUnion = Foo | Biz | Bar
            '),
            $this->printTestSchemaChanges($extendedSchema),
        );

        $someUnionType = $extendedSchema->getType('SomeUnion');
        $unionField    = $extendedSchema->getQueryType()->getField('someUnion');
        self::assertEquals($unionField->getType(), $someUnionType);
    }

    /**
     * @see it('allows extension of union by adding itself')
     */
    public function testAllowsExtensionOfUnionByAddingItself(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend union SomeUnion = SomeUnion
        ');

        $errors = $extendedSchema->validate();
        self::assertGreaterThan(0, count($errors));

        self::assertEquals(
            $this->dedent('
                union SomeUnion = Foo | Biz | SomeUnion
            '),
            $this->printTestSchemaChanges($extendedSchema),
        );
    }

    /**
     * @see it('extends inputs by adding new fields')
     */
    public function testExtendsInputsByAddingNewFields(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend input SomeInput {
            newField: String
          }
        ');

        self::assertEquals(
            $this->dedent('
              input SomeInput {
                fooArg: String
                newField: String
              }
            '),
            $this->printTestSchemaChanges($extendedSchema),
        );

        $someInputType = $extendedSchema->getType('SomeInput');
        $inputField    = $extendedSchema->getQueryType()->getField('someInput');
        self::assertEquals($inputField->args[0]->getType(), $someInputType);

        $fooDirective = $extendedSchema->getDirective('foo');
        self::assertEquals($fooDirective->args[0]->getType(), $someInputType);
    }

    /**
     * @see it('extends scalars by adding new directives')
     */
    public function testExtendsScalarsByAddingNewDirectives(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend scalar SomeScalar @foo
        ');

        $someScalar = $extendedSchema->getType('SomeScalar');
        self::assertCount(1, $someScalar->extensionASTNodes);
        self::assertEquals(
            'extend scalar SomeScalar @foo',
            Printer::doPrint($someScalar->extensionASTNodes[0]),
        );
    }

    /**
     * @see it('correctly assign AST nodes to new and extended types')
     */
    public function testCorrectlyAssignASTNodesToNewAndExtendedTypes(): void
    {
        $extendedSchema = $this->extendTestSchema('
              extend type Query {
                newField(testArg: TestInput): TestEnum
              }
              extend scalar SomeScalar @foo
              extend enum SomeEnum {
                NEW_VALUE
              }
              extend union SomeUnion = Bar
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

        $ast = Parser::parse('
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

        $extendedTwiceSchema = SchemaExtender::extend($extendedSchema, $ast);
        $query               = $extendedTwiceSchema->getQueryType();
        /** @var ScalarType $someScalar */
        $someScalar = $extendedTwiceSchema->getType('SomeScalar');
        /** @var EnumType $someEnum */
        $someEnum = $extendedTwiceSchema->getType('SomeEnum');
        /** @var UnionType $someUnion */
        $someUnion = $extendedTwiceSchema->getType('SomeUnion');
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
        /** @var InterfaceType $testInterface */
        $testInterface = $extendedTwiceSchema->getType('TestInterface');
        /** @var ObjectType $testType */
        $testType = $extendedTwiceSchema->getType('TestType');
        /** @var Directive $testDirective */
        $testDirective = $extendedTwiceSchema->getDirective('test');

        self::assertCount(2, $query->extensionASTNodes);
        self::assertCount(2, $someScalar->extensionASTNodes);
        self::assertCount(2, $someEnum->extensionASTNodes);
        self::assertCount(2, $someUnion->extensionASTNodes);
        self::assertCount(2, $someInput->extensionASTNodes);
        self::assertCount(2, $someInterface->extensionASTNodes);

        self::assertCount(0, $testType->extensionASTNodes);
        self::assertCount(0, $testEnum->extensionASTNodes);
        self::assertCount(0, $testUnion->extensionASTNodes);
        self::assertCount(0, $testInput->extensionASTNodes);
        self::assertCount(0, $testInterface->extensionASTNodes);

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

        self::assertEquals(
            SchemaPrinter::doPrint(SchemaExtender::extend($this->testSchema, $restoredExtensionAST)),
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
        $extendedSchema = $this->extendTestSchema('
            type TypeWithDeprecatedField {
                newDeprecatedField: String @deprecated(reason: "not used anymore")
            }
            enum EnumWithDeprecatedValue {
                DEPRECATED @deprecated(reason: "do not use")
            }
        ');

        /** @var ObjectType $typeWithDeprecatedField */
        $typeWithDeprecatedField = $extendedSchema->getType('TypeWithDeprecatedField');
        $deprecatedFieldDef      = $typeWithDeprecatedField->getField('newDeprecatedField');

        self::assertEquals(true, $deprecatedFieldDef->isDeprecated());
        self::assertEquals('not used anymore', $deprecatedFieldDef->deprecationReason);

        /** @var EnumType $enumWithDeprecatedValue */
        $enumWithDeprecatedValue = $extendedSchema->getType('EnumWithDeprecatedValue');
        $deprecatedEnumDef       = $enumWithDeprecatedValue->getValue('DEPRECATED');

        self::assertEquals(true, $deprecatedEnumDef->isDeprecated());
        self::assertEquals('do not use', $deprecatedEnumDef->deprecationReason);
    }

    /**
     * @see it('extends objects with deprecated fields')
     */
    public function testExtendsObjectsWithDeprecatedFields(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend type Foo {
            deprecatedField: String @deprecated(reason: "not used anymore")
          }
        ');
        /** @var ObjectType $fooType */
        $fooType            = $extendedSchema->getType('Foo');
        $deprecatedFieldDef = $fooType->getField('deprecatedField');

        self::assertTrue($deprecatedFieldDef->isDeprecated());
        self::assertEquals('not used anymore', $deprecatedFieldDef->deprecationReason);
    }

    /**
     * @see it('extends enums with deprecated values')
     */
    public function testExtendsEnumsWithDeprecatedValues(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend enum SomeEnum {
            DEPRECATED @deprecated(reason: "do not use")
          }
        ');

        /** @var EnumType $someEnumType */
        $someEnumType      = $extendedSchema->getType('SomeEnum');
        $deprecatedEnumDef = $someEnumType->getValue('DEPRECATED');

        self::assertTrue($deprecatedEnumDef->isDeprecated());
        self::assertEquals('do not use', $deprecatedEnumDef->deprecationReason);
    }

    /**
     * @see it('adds new unused object type')
     */
    public function testAddsNewUnusedObjectType(): void
    {
        $extendedSchema = $this->extendTestSchema('
          type Unused {
            someField: String
          }
        ');
        self::assertNotEquals($this->testSchema, $extendedSchema);
        self::assertEquals(
            $this->dedent('
                type Unused {
                  someField: String
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('adds new unused enum type')
     */
    public function testAddsNewUnusedEnumType(): void
    {
        $extendedSchema = $this->extendTestSchema('
          enum UnusedEnum {
            SOME
          }
        ');
        self::assertNotEquals($extendedSchema, $this->testSchema);
        self::assertEquals(
            $this->dedent('
                enum UnusedEnum {
                  SOME
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('adds new unused input object type')
     */
    public function testAddsNewUnusedInputObjectType(): void
    {
        $extendedSchema = $this->extendTestSchema('
          input UnusedInput {
            someInput: String
          }
        ');

        self::assertNotEquals($extendedSchema, $this->testSchema);
        self::assertEquals(
            $this->dedent('
              input UnusedInput {
                someInput: String
              }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('adds new union using new object type')
     */
    public function testAddsNewUnionUsingNewObjectType(): void
    {
        $extendedSchema = $this->extendTestSchema('
          type DummyUnionMember {
            someField: String
          }

          union UnusedUnion = DummyUnionMember
        ');

        self::assertNotEquals($extendedSchema, $this->testSchema);
        self::assertEquals(
            $this->dedent('
              type DummyUnionMember {
                someField: String
              }

              union UnusedUnion = DummyUnionMember
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('extends objects by adding new fields with arguments')
     */
    public function testExtendsObjectsByAddingNewFieldsWithArguments(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend type Foo {
            newField(arg1: String, arg2: NewInputObj!): String
          }

          input NewInputObj {
            field1: Int
            field2: [Float]
            field3: String!
          }
        ');

        self::assertEquals(
            $this->dedent('
                type Foo implements AnotherInterface & SomeInterface {
                  name: String
                  some: AnotherInterface
                  tree: [Foo]!
                  newField(arg1: String, arg2: NewInputObj!): String
                }

                input NewInputObj {
                  field1: Int
                  field2: [Float]
                  field3: String!
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('extends objects by adding new fields with existing types')
     */
    public function testExtendsObjectsByAddingNewFieldsWithExistingTypes(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend type Foo {
            newField(arg1: SomeEnum!): SomeEnum
          }
        ');

        self::assertEquals(
            $this->dedent('
                type Foo implements AnotherInterface & SomeInterface {
                  name: String
                  some: AnotherInterface
                  tree: [Foo]!
                  newField(arg1: SomeEnum!): SomeEnum
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('extends objects by adding implemented interfaces')
     */
    public function testExtendsObjectsByAddingImplementedInterfaces(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend type Biz implements SomeInterface {
            name: String
            some: SomeInterface
          }
        ');

        self::assertEquals(
            $this->dedent('
                type Biz implements SomeInterface {
                  fizz: String
                  name: String
                  some: SomeInterface
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('extends objects by including new types')
     */
    public function testExtendsObjectsByIncludingNewTypes(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend type Foo {
            newObject: NewObject
            newInterface: NewInterface
            newUnion: NewUnion
            newScalar: NewScalar
            newEnum: NewEnum
            newTree: [Foo]!
          }

          type NewObject implements NewInterface {
            baz: String
          }

          type NewOtherObject {
            fizz: Int
          }

          interface NewInterface {
            baz: String
          }

          union NewUnion = NewObject | NewOtherObject

          scalar NewScalar

          enum NewEnum {
            OPTION_A
            OPTION_B
          }
        ');

        self::assertEquals(
            $this->dedent('
                type Foo implements AnotherInterface & SomeInterface {
                  name: String
                  some: AnotherInterface
                  tree: [Foo]!
                  newObject: NewObject
                  newInterface: NewInterface
                  newUnion: NewUnion
                  newScalar: NewScalar
                  newEnum: NewEnum
                  newTree: [Foo]!
                }

                enum NewEnum {
                  OPTION_A
                  OPTION_B
                }

                interface NewInterface {
                  baz: String
                }

                type NewObject implements NewInterface {
                  baz: String
                }

                type NewOtherObject {
                  fizz: Int
                }

                scalar NewScalar

                union NewUnion = NewObject | NewOtherObject
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('extends objects by adding implemented new interfaces')
     */
    public function testExtendsObjectsByAddingImplementedNewInterfaces(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend type Foo implements NewInterface {
            baz: String
          }

          interface NewInterface {
            baz: String
          }
        ');

        self::assertEquals(
            $this->dedent('
                type Foo implements AnotherInterface & SomeInterface & NewInterface {
                  name: String
                  some: AnotherInterface
                  tree: [Foo]!
                  baz: String
                }

                interface NewInterface {
                  baz: String
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('extends different types multiple times')
     */
    public function testExtendsDifferentTypesMultipleTimes(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend type Biz implements NewInterface {
            buzz: String
          }

          extend type Biz implements SomeInterface {
            name: String
            some: SomeInterface
            newFieldA: Int
          }

          extend type Biz {
            newFieldB: Float
          }

          interface NewInterface {
            buzz: String
          }

          extend enum SomeEnum {
            THREE
          }

          extend enum SomeEnum {
            FOUR
          }

          extend union SomeUnion = Boo

          extend union SomeUnion = Joo

          type Boo {
            fieldA: String
          }

          type Joo {
            fieldB: String
          }

          extend input SomeInput {
            fieldA: String
          }

          extend input SomeInput {
            fieldB: String
          }
        ');

        self::assertEquals(
            $this->dedent('
                type Biz implements NewInterface & SomeInterface {
                  fizz: String
                  buzz: String
                  name: String
                  some: SomeInterface
                  newFieldA: Int
                  newFieldB: Float
                }

                type Boo {
                  fieldA: String
                }

                type Joo {
                  fieldB: String
                }

                interface NewInterface {
                  buzz: String
                }

                enum SomeEnum {
                  ONE
                  TWO
                  THREE
                  FOUR
                }

                input SomeInput {
                  fooArg: String
                  fieldA: String
                  fieldB: String
                }

                union SomeUnion = Foo | Biz | Boo | Joo
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('extends interfaces by adding new fields')
     */
    public function testExtendsInterfacesByAddingNewFields(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend interface SomeInterface {
            newField: String
          }
          
          extend interface AnotherInterface {
            newField: String
          }

          extend type Bar {
            newField: String
          }

          extend type Foo {
            newField: String
          }
        ');

        self::assertEquals(
            $this->dedent('
                interface AnotherInterface implements SomeInterface {
                  name: String
                  some: AnotherInterface
                  newField: String
                }

                type Bar implements SomeInterface {
                  some: SomeInterface
                  foo: Foo
                  newField: String
                }

                type Foo implements AnotherInterface & SomeInterface {
                  name: String
                  some: AnotherInterface
                  tree: [Foo]!
                  newField: String
                }

                interface SomeInterface {
                  some: SomeInterface
                  newField: String
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('extends interfaces by adding new implemted interfaces')
     */
    public function testExtendsInterfacesByAddingNewImplementedInterfaces(): void
    {
        $extendedSchema = $this->extendTestSchema('
          interface NewInterface {
            newField: String
          }
          
          extend interface AnotherInterface implements NewInterface {
            newField: String
          }
          
          extend type Foo implements NewInterface {
            newField: String
          }
        ');

        self::assertEquals(
            $this->dedent('
                interface AnotherInterface implements SomeInterface & NewInterface {
                  name: String
                  some: AnotherInterface
                  newField: String
                }
                
                type Foo implements AnotherInterface & SomeInterface & NewInterface {
                  name: String
                  some: AnotherInterface
                  tree: [Foo]!
                  newField: String
                }
                
                interface NewInterface {
                  newField: String
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('allows extension of interface with missing Object fields')
     */
    public function testAllowsExtensionOfInterfaceWithMissingObjectFields(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend interface SomeInterface {
            newField: String
          }
        ');

        $errors = $extendedSchema->validate();
        self::assertGreaterThan(0, $errors);

        self::assertEquals(
            $this->dedent('
                interface SomeInterface {
                  some: SomeInterface
                  newField: String
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('extends interfaces multiple times')
     */
    public function testExtendsInterfacesMultipleTimes(): void
    {
        $extendedSchema = $this->extendTestSchema('
          extend interface SomeInterface {
            newFieldA: Int
          }
          
          extend interface SomeInterface {
            newFieldB(test: Boolean): String
          }
        ');

        self::assertEquals(
            $this->dedent('
                interface SomeInterface {
                  some: SomeInterface
                  newFieldA: Int
                  newFieldB(test: Boolean): String
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('may extend mutations and subscriptions')
     */
    public function testMayExtendMutationsAndSubscriptions(): void
    {
        $mutationSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => static function (): array {
                    return ['queryField' => ['type' => Type::string()]];
                },
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => static function (): array {
                    return ['mutationField' => ['type' => Type::string()]];
                },
            ]),
            'subscription' => new ObjectType([
                'name' => 'Subscription',
                'fields' => static function (): array {
                    return ['subscriptionField' => ['type' => Type::string()]];
                },
            ]),
        ]);

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
                type Mutation {
                  mutationField: String
                  newMutationField: Int
                }

                type Query {
                  queryField: String
                  newQueryField: Int
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
     * @see it('may extend directives with new simple directive')
     */
    public function testMayExtendDirectivesWithNewSimpleDirective(): void
    {
        $extendedSchema = $this->extendTestSchema('
          directive @neat on QUERY
        ');

        $newDirective = $extendedSchema->getDirective('neat');
        self::assertEquals('neat', $newDirective->name);
        self::assertContains('QUERY', $newDirective->locations);
    }

    /**
     * @see it('sets correct description when extending with a new directive')
     */
    public function testSetsCorrectDescriptionWhenExtendingWithANewDirective(): void
    {
        $extendedSchema = $this->extendTestSchema('
          """
          new directive
          """
          directive @new on QUERY
        ');

        $newDirective = $extendedSchema->getDirective('new');
        self::assertEquals('new directive', $newDirective->description);
    }

    /**
     * @see it('sets correct description using legacy comments')
     */
    public function testSetsCorrectDescriptionUsingLegacyComments(): void
    {
        $extendedSchema = $this->extendTestSchema(
            '
            # new directive
            directive @new on QUERY
            ',
            ['commentDescriptions' => true]
        );

        $newDirective = $extendedSchema->getDirective('new');
        self::assertEquals('new directive', $newDirective->description);
    }

    /**
     * @see it('may extend directives with new complex directive')
     */
    public function testMayExtendDirectivesWithNewComplexDirective(): void
    {
        $extendedSchema = $this->extendTestSchema('
          directive @profile(enable: Boolean! tag: String) repeatable on QUERY | FIELD
        ');

        $extendedDirective = $extendedSchema->getDirective('profile');
        self::assertContains('QUERY', $extendedDirective->locations);
        self::assertContains('FIELD', $extendedDirective->locations);

        $args = $extendedDirective->args;
        self::assertCount(2, $args);

        $arg0 = $args[0];
        $arg1 = $args[1];
        /** @var NonNull $arg0Type */
        $arg0Type = $arg0->getType();

        self::assertEquals('enable', $arg0->name);
        self::assertInstanceOf(NonNull::class, $arg0Type);
        self::assertInstanceOf(ScalarType::class, $arg0Type->getWrappedType());

        self::assertEquals('tag', $arg1->name);
        self::assertInstanceOf(ScalarType::class, $arg1->getType());
    }

    /**
     * @see it('Rejects invalid SDL')
     */
    public function testRejectsInvalidSDL(): void
    {
        $sdl = '
            extend schema @unknown
        ';

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Unknown directive "unknown".');

        $this->extendTestSchema($sdl);
    }

    /**
     * @see it('Allows to disable SDL validation')
     */
    public function testAllowsToDisableSDLValidation(): void
    {
        $sdl = '
          extend schema @unknown
        ';

        $this->extendTestSchema($sdl, ['assumeValid' => true]);
        $this->extendTestSchema($sdl, ['assumeValidSDL' => true]);
    }

    /**
     * @see it('does not allow replacing a default directive')
     */
    public function testDoesNotAllowReplacingADefaultDirective(): void
    {
        $sdl = '
          directive @include(if: Boolean!) on FIELD | FRAGMENT_SPREAD
        ';

        try {
            $this->extendTestSchema($sdl);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Directive "include" already exists in the schema. It cannot be redefined.', $error->getMessage());
        }
    }

    /**
     * @see it('does not allow replacing an existing enum value')
     */
    public function testDoesNotAllowReplacingAnExistingEnumValue(): void
    {
        $sdl = '
          extend enum SomeEnum {
            ONE
          }
        ';

        try {
            $this->extendTestSchema($sdl);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Enum value "SomeEnum.ONE" already exists in the schema. It cannot also be defined in this type extension.', $error->getMessage());
        }
    }

    // describe('can add additional root operation types')

    /**
     * @see it('does not automatically include common root type names')
     */
    public function testDoesNotAutomaticallyIncludeCommonRootTypeNames(): void
    {
        $schema = $this->extendTestSchema('
            type Mutation
        ');

        self::assertNull($schema->getMutationType());
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

        $schema    = SchemaExtender::extend($schema, Parser::parse($extensionSDL));
        $queryType = $schema->getQueryType();

        self::assertEquals('Foo', $queryType->name);
        self::assertEquals($extensionSDL, $this->printASTSchema($schema));
    }

    /**
     * @see it('adds new root types via schema extension')
     */
    public function testAddsNewRootTypesViaSchemaExtension(): void
    {
        $schema = $this->extendTestSchema('
            extend schema {
              mutation: Mutation
            }
            type Mutation
        ');

        $mutationType = $schema->getMutationType();
        self::assertEquals('Mutation', $mutationType->name);
    }

    /**
     * @see it('adds multiple new root types via schema extension')
     */
    public function testAddsMultipleNewRootTypesViaSchemaExtension(): void
    {
        $schema = $this->extendTestSchema('
            extend schema {
              mutation: Mutation
              subscription: Subscription
            }
            type Mutation
            type Subscription
        ');

        $mutationType     = $schema->getMutationType();
        $subscriptionType = $schema->getSubscriptionType();

        self::assertEquals('Mutation', $mutationType->name);
        self::assertEquals('Subscription', $subscriptionType->name);
    }

    /**
     * @see it('applies multiple schema extensions')
     */
    public function testAppliesMultipleSchemaExtensions(): void
    {
        $schema = $this->extendTestSchema('
            extend schema {
              mutation: Mutation
            }
            type Mutation

            extend schema {
              subscription: Subscription
            }
            type Subscription
        ');

        $mutationType     = $schema->getMutationType();
        $subscriptionType = $schema->getSubscriptionType();

        self::assertEquals('Mutation', $mutationType->name);
        self::assertEquals('Subscription', $subscriptionType->name);
    }

    /**
     * @see it('schema extension AST are available from schema object')
     */
    public function testSchemaExtensionASTAreAvailableFromSchemaObject(): void
    {
        $schema = $this->extendTestSchema('
            extend schema {
              mutation: Mutation
            }
            type Mutation

            extend schema {
              subscription: Subscription
            }
            type Subscription
        ');

        $ast    = Parser::parse('
            extend schema @foo
        ');
        $schema = SchemaExtender::extend($schema, $ast);

        $nodes = $schema->extensionASTNodes;
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
            implode(
                "\n",
                array_map(static function ($node): string {
                    return Printer::doPrint($node) . "\n";
                }, $nodes)
            )
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
