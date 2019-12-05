<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldArgument;
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
use PHPUnit\Framework\TestCase;
use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function implode;
use function in_array;
use function iterator_to_array;
use function preg_match;
use function preg_replace;
use function trim;

class SchemaExtenderTest extends TestCase
{
    /** @var Schema */
    protected $testSchema;

    /** @var string[] */
    protected $testSchemaDefinitions;

    /** @var ObjectType */
    protected $FooType;

    /** @var Directive */
    protected $FooDirective;

    public function setUp()
    {
        parent::setUp();

        $SomeScalarType = new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => static function ($x) {
                return $x;
            },
        ]);

        $SomeInterfaceType = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => static function () use (&$SomeInterfaceType) {
                return [
                    'name' => [ 'type' => Type::string()],
                    'some' => [ 'type' => $SomeInterfaceType],
                ];
            },
        ]);

        $FooType = new ObjectType([
            'name' => 'Foo',
            'interfaces' => [$SomeInterfaceType],
            'fields' => static function () use ($SomeInterfaceType, &$FooType) {
                return [
                    'name' => [ 'type' => Type::string() ],
                    'some' => [ 'type' => $SomeInterfaceType ],
                    'tree' => [ 'type' => Type::nonNull(Type::listOf($FooType))],
                ];
            },
        ]);

        $BarType = new ObjectType([
            'name' => 'Bar',
            'interfaces' => [$SomeInterfaceType],
            'fields' => static function () use ($SomeInterfaceType, $FooType) : array {
                return [
                    'name' => [ 'type' => Type::string() ],
                    'some' => [ 'type' => $SomeInterfaceType ],
                    'foo' => [ 'type' => $FooType ],
                ];
            },
        ]);

        $BizType = new ObjectType([
            'name' => 'Biz',
            'fields' => static function () : array {
                return [
                    'fizz' => [ 'type' => Type::string() ],
                ];
            },
        ]);

        $SomeUnionType = new UnionType([
            'name' => 'SomeUnion',
            'types' => [$FooType, $BizType],
        ]);

        $SomeEnumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'ONE' => [ 'value' => 1 ],
                'TWO' => [ 'value' => 2 ],
            ],
        ]);

        $SomeInputType = new InputObjectType([
            'name' => 'SomeInput',
            'fields' => static function () : array {
                return [
                    'fooArg' => [ 'type' => Type::string() ],
                ];
            },
        ]);

        $FooDirective = new Directive([
            'name' => 'foo',
            'args' => [
                new FieldArgument([
                    'name' => 'input',
                    'type' => $SomeInputType,
                ]),
            ],
            'locations' => [
                DirectiveLocation::SCHEMA,
                DirectiveLocation::SCALAR,
                DirectiveLocation::OBJECT,
                DirectiveLocation::FIELD_DEFINITION,
                DirectiveLocation::ARGUMENT_DEFINITION,
                DirectiveLocation::IFACE,
                DirectiveLocation::UNION,
                DirectiveLocation::ENUM,
                DirectiveLocation::ENUM_VALUE,
                DirectiveLocation::INPUT_OBJECT,
                DirectiveLocation::INPUT_FIELD_DEFINITION,
            ],
        ]);

        $this->testSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => static function () use ($FooType, $SomeScalarType, $SomeUnionType, $SomeEnumType, $SomeInterfaceType, $SomeInputType) : array {
                    return [
                        'foo' => [ 'type' => $FooType ],
                        'someScalar' => [ 'type' => $SomeScalarType ],
                        'someUnion' => [ 'type' => $SomeUnionType ],
                        'someEnum' => [ 'type' => $SomeEnumType ],
                        'someInterface' => [
                            'args' => [
                                'id' => [
                                    'type' => Type::nonNull(Type::id()),
                                ],
                            ],
                            'type' => $SomeInterfaceType,
                        ],
                        'someInput' => [
                            'args' => [ 'input' => [ 'type' => $SomeInputType ] ],
                            'type' => Type::string(),
                        ],
                    ];
                },
            ]),
            'types' => [$FooType, $BarType],
            'directives' => array_merge(GraphQL::getStandardDirectives(), [$FooDirective]),
        ]);

        $testSchemaAst = Parser::parse(SchemaPrinter::doPrint($this->testSchema));

        $this->testSchemaDefinitions = array_map(static function ($node) {
            return Printer::doPrint($node);
        }, iterator_to_array($testSchemaAst->definitions->getIterator()));

        $this->FooDirective = $FooDirective;
        $this->FooType      = $FooType;
    }

    protected function dedent(string $str) : string
    {
        $trimmedStr = trim($str, "\n");
        $trimmedStr = preg_replace('/[ \t]*$/', '', $trimmedStr);

        preg_match('/^[ \t]*/', $trimmedStr, $indentMatch);
        $indent = $indentMatch[0];

        return preg_replace('/^' . $indent . '/m', '', $trimmedStr);
    }

    /**
     * @param mixed[]|null $options
     */
    protected function extendTestSchema(string $sdl, ?array $options = null) : Schema
    {
        $originalPrint  = SchemaPrinter::doPrint($this->testSchema);
        $ast            = Parser::parse($sdl);
        $extendedSchema = SchemaExtender::extend($this->testSchema, $ast, $options);
        self::assertEquals(SchemaPrinter::doPrint($this->testSchema), $originalPrint);

        return $extendedSchema;
    }

    protected function printTestSchemaChanges(Schema $extendedSchema) : string
    {
        $ast = Parser::parse(SchemaPrinter::doPrint($extendedSchema));
        /** @var array<Node&DefinitionNode> $extraDefinitions */
        $extraDefinitions = array_values(array_filter(
            iterator_to_array($ast->definitions->getIterator()),
            function (Node $node) : bool {
                return ! in_array(Printer::doPrint($node), $this->testSchemaDefinitions, true);
            }
        ));
        $ast->definitions = new NodeList($extraDefinitions);

        return Printer::doPrint($ast);
    }

    /**
     * @see it('returns the original schema when there are no type definitions')
     */
    public function testReturnsTheOriginalSchemaWhenThereAreNoTypeDefinitions()
    {
        $extendedSchema = $this->extendTestSchema('{ field }');
        self::assertEquals($extendedSchema, $this->testSchema);
    }

    /**
     * @see it('extends without altering original schema')
     */
    public function testExtendsWithoutAlteringOriginalSchema()
    {
        $extendedSchema = $this->extendTestSchema('
            extend type Query {
                newField: String
            }');
        self::assertNotEquals($extendedSchema, $this->testSchema);
        self::assertContains('newField', SchemaPrinter::doPrint($extendedSchema));
        self::assertNotContains('newField', SchemaPrinter::doPrint($this->testSchema));
    }

    /**
     * @see it('can be used for limited execution')
     */
    public function testCanBeUsedForLimitedExecution()
    {
        $extendedSchema = $this->extendTestSchema('
          extend type Query {
            newField: String
          }
        ');

        $result = GraphQL::executeQuery($extendedSchema, '{ newField }', ['newField' => 123]);

        self::assertEquals($result->toArray(), [
            'data' => ['newField' => '123'],
        ]);
    }

    /**
     * @see it('can describe the extended fields')
     */
    public function testCanDescribeTheExtendedFields()
    {
        $extendedSchema = $this->extendTestSchema('
            extend type Query {
                "New field description."
                newField: String
            }
        ');

        self::assertEquals(
            $extendedSchema->getQueryType()->getField('newField')->description,
            'New field description.'
        );
    }

    /**
     * @see it('can describe the extended fields with legacy comments')
     */
    public function testCanDescribeTheExtendedFieldsWithLegacyComments()
    {
        $extendedSchema = $this->extendTestSchema('
            extend type Query {
                # New field description.
                newField: String
            }
        ', ['commentDescriptions' => true]);

        self::assertEquals(
            $extendedSchema->getQueryType()->getField('newField')->description,
            'New field description.'
        );
    }

    /**
     * @see it('describes extended fields with strings when present')
     */
    public function testDescribesExtendedFieldsWithStringsWhenPresent()
    {
        $extendedSchema = $this->extendTestSchema('
            extend type Query {
                # New field description.
                "Actually use this description."
                newField: String
            }
        ', ['commentDescriptions' => true ]);

        self::assertEquals(
            $extendedSchema->getQueryType()->getField('newField')->description,
            'Actually use this description.'
        );
    }

    /**
     * @see it('extends objects by adding new fields')
     */
    public function testExtendsObjectsByAddingNewFields()
    {
        $extendedSchema = $this->extendTestSchema(
            '
            extend type Foo {
                newField: String
            }
        '
        );

        self::assertEquals(
            $this->printTestSchemaChanges($extendedSchema),
            $this->dedent('
              type Foo implements SomeInterface {
                name: String
                some: SomeInterface
                tree: [Foo]!
                newField: String
              }
            ')
        );

        $fooType  = $extendedSchema->getType('Foo');
        $fooField = $extendedSchema->getQueryType()->getField('foo');
        self::assertEquals($fooField->getType(), $fooType);
    }

    /**
     * @see it('extends enums by adding new values')
     */
    public function testExtendsEnumsByAddingNewValues()
    {
        $extendedSchema = $this->extendTestSchema('
          extend enum SomeEnum {
            NEW_ENUM
          }
        ');

        self::assertEquals(
            $this->printTestSchemaChanges($extendedSchema),
            $this->dedent('
              enum SomeEnum {
                ONE
                TWO
                NEW_ENUM
              }
          ')
        );

        $someEnumType = $extendedSchema->getType('SomeEnum');
        $enumField    = $extendedSchema->getQueryType()->getField('someEnum');
        self::assertEquals($enumField->getType(), $someEnumType);
    }

    /**
     * @see it('extends unions by adding new types')
     */
    public function testExtendsUnionsByAddingNewTypes()
    {
        $extendedSchema = $this->extendTestSchema('
          extend union SomeUnion = Bar
        ');
        self::assertEquals(
            $this->printTestSchemaChanges($extendedSchema),
            $this->dedent('
              union SomeUnion = Foo | Biz | Bar
            ')
        );

        $someUnionType = $extendedSchema->getType('SomeUnion');
        $unionField    = $extendedSchema->getQueryType()->getField('someUnion');
        self::assertEquals($unionField->getType(), $someUnionType);
    }

    /**
     * @see it('allows extension of union by adding itself')
     */
    public function testAllowsExtensionOfUnionByAddingItself()
    {
        $extendedSchema = $this->extendTestSchema('
          extend union SomeUnion = SomeUnion
        ');

        $errors = $extendedSchema->validate();
        self::assertGreaterThan(0, count($errors));

        self::assertEquals(
            $this->printTestSchemaChanges($extendedSchema),
            $this->dedent('
                union SomeUnion = Foo | Biz | SomeUnion
            ')
        );
    }

    /**
     * @see it('extends inputs by adding new fields')
     */
    public function testExtendsInputsByAddingNewFields()
    {
        $extendedSchema = $this->extendTestSchema('
          extend input SomeInput {
            newField: String
          }
        ');

        self::assertEquals(
            $this->printTestSchemaChanges($extendedSchema),
            $this->dedent('
              input SomeInput {
                fooArg: String
                newField: String
              }
            ')
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
    public function testExtendsScalarsByAddingNewDirectives()
    {
        $extendedSchema = $this->extendTestSchema('
          extend scalar SomeScalar @foo
        ');

        $someScalar = $extendedSchema->getType('SomeScalar');
        self::assertCount(1, $someScalar->extensionASTNodes);
        self::assertEquals(
            Printer::doPrint($someScalar->extensionASTNodes[0]),
            'extend scalar SomeScalar @foo'
        );
    }

    /**
     * @see it('correctly assign AST nodes to new and extended types')
     */
    public function testCorrectlyAssignASTNodesToNewAndExtendedTypes()
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
            directive @test(arg: Int) on FIELD | SCALAR
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

        self::assertCount(0, $testType->extensionASTNodes ?? []);
        self::assertCount(0, $testEnum->extensionASTNodes ?? []);
        self::assertCount(0, $testUnion->extensionASTNodes ?? []);
        self::assertCount(0, $testInput->extensionASTNodes ?? []);
        self::assertCount(0, $testInterface->extensionASTNodes ?? []);

        $restoredExtensionAST = new DocumentNode([
            'definitions' => array_merge(
                $query->extensionASTNodes,
                $someScalar->extensionASTNodes,
                $someEnum->extensionASTNodes,
                $someUnion->extensionASTNodes,
                $someInput->extensionASTNodes,
                $someInterface->extensionASTNodes,
                [
                    $testInput->astNode,
                    $testEnum->astNode,
                    $testUnion->astNode,
                    $testInterface->astNode,
                    $testType->astNode,
                    $testDirective->astNode,
                ]
            ),
        ]);

        self::assertEquals(
            SchemaPrinter::doPrint(SchemaExtender::extend($this->testSchema, $restoredExtensionAST)),
            SchemaPrinter::doPrint($extendedTwiceSchema)
        );

        $newField = $query->getField('newField');

        self::assertEquals(Printer::doPrint($newField->astNode), 'newField(testArg: TestInput): TestEnum');
        self::assertEquals(Printer::doPrint($newField->args[0]->astNode), 'testArg: TestInput');
        self::assertEquals(Printer::doPrint($query->getField('oneMoreNewField')->astNode), 'oneMoreNewField: TestUnion');
        self::assertEquals(Printer::doPrint($someEnum->getValue('NEW_VALUE')->astNode), 'NEW_VALUE');
        self::assertEquals(Printer::doPrint($someEnum->getValue('ONE_MORE_NEW_VALUE')->astNode), 'ONE_MORE_NEW_VALUE');
        self::assertEquals(Printer::doPrint($someInput->getField('newField')->astNode), 'newField: String');
        self::assertEquals(Printer::doPrint($someInput->getField('oneMoreNewField')->astNode), 'oneMoreNewField: String');
        self::assertEquals(Printer::doPrint($someInterface->getField('newField')->astNode), 'newField: String');
        self::assertEquals(Printer::doPrint($someInterface->getField('oneMoreNewField')->astNode), 'oneMoreNewField: String');
        self::assertEquals(Printer::doPrint($testInput->getField('testInputField')->astNode), 'testInputField: TestEnum');
        self::assertEquals(Printer::doPrint($testEnum->getValue('TEST_VALUE')->astNode), 'TEST_VALUE');
        self::assertEquals(Printer::doPrint($testInterface->getField('interfaceField')->astNode), 'interfaceField: String');
        self::assertEquals(Printer::doPrint($testType->getField('interfaceField')->astNode), 'interfaceField: String');
        self::assertEquals(Printer::doPrint($testDirective->args[0]->astNode), 'arg: Int');
    }

    /**
     * @see it('builds types with deprecated fields/values')
     */
    public function testBuildsTypesWithDeprecatedFieldsOrValues()
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
    public function testExtendsObjectsWithDeprecatedFields()
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
    public function testExtendsEnumsWithDeprecatedValues()
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
    public function testAddsNewUnusedObjectType()
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
    public function testAddsNewUnusedEnumType()
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
    public function testAddsNewUnusedInputObjectType()
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
    public function testAddsNewUnionUsingNewObjectType()
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
    public function testExtendsObjectsByAddingNewFieldsWithArguments()
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
                type Foo implements SomeInterface {
                  name: String
                  some: SomeInterface
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
    public function testExtendsObjectsByAddingNewFieldsWithExistingTypes()
    {
        $extendedSchema = $this->extendTestSchema('
          extend type Foo {
            newField(arg1: SomeEnum!): SomeEnum
          }
        ');

        self::assertEquals(
            $this->dedent('
                type Foo implements SomeInterface {
                  name: String
                  some: SomeInterface
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
    public function testExtendsObjectsByAddingImplementedInterfaces()
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
    public function testExtendsObjectsByIncludingNewTypes()
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
                type Foo implements SomeInterface {
                  name: String
                  some: SomeInterface
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
    public function testExtendsObjectsByAddingImplementedNewInterfaces()
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
                type Foo implements SomeInterface & NewInterface {
                  name: String
                  some: SomeInterface
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
    public function testExtendsDifferentTypesMultipleTimes()
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
            newFieldA: Int
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
    public function testExtendsInterfacesByAddingNewFields()
    {
        $extendedSchema = $this->extendTestSchema('
          extend interface SomeInterface {
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
                type Bar implements SomeInterface {
                  name: String
                  some: SomeInterface
                  foo: Foo
                  newField: String
                }

                type Foo implements SomeInterface {
                  name: String
                  some: SomeInterface
                  tree: [Foo]!
                  newField: String
                }

                interface SomeInterface {
                  name: String
                  some: SomeInterface
                  newField: String
                }
            '),
            $this->printTestSchemaChanges($extendedSchema)
        );
    }

    /**
     * @see it('allows extension of interface with missing Object fields')
     */
    public function testAllowsExtensionOfInterfaceWithMissingObjectFields()
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
                  name: String
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
    public function testExtendsInterfacesMultipleTimes()
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
                  name: String
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
    public function testMayExtendMutationsAndSubscriptions()
    {
        $mutationSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => static function () {
                    return [ 'queryField' => [ 'type' => Type::string() ] ];
                },
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => static function () {
                    return [ 'mutationField' => ['type' => Type::string() ] ];
                },
            ]),
            'subscription' => new ObjectType([
                'name' => 'Subscription',
                'fields' => static function () {
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
        self::assertEquals(SchemaPrinter::doPrint($mutationSchema), $originalPrint);
        self::assertEquals(SchemaPrinter::doPrint($extendedSchema), $this->dedent('
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
        '));
    }

    /**
     * @see it('may extend directives with new simple directive')
     */
    public function testMayExtendDirectivesWithNewSimpleDirective()
    {
        $extendedSchema = $this->extendTestSchema('
          directive @neat on QUERY
        ');

        $newDirective = $extendedSchema->getDirective('neat');
        self::assertEquals($newDirective->name, 'neat');
        self::assertContains('QUERY', $newDirective->locations);
    }

    /**
     * @see it('sets correct description when extending with a new directive')
     */
    public function testSetsCorrectDescriptionWhenExtendingWithANewDirective()
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
    public function testSetsCorrectDescriptionUsingLegacyComments()
    {
        $extendedSchema = $this->extendTestSchema(
            '
          # new directive
          directive @new on QUERY
        ',
            [ 'commentDescriptions' => true ]
        );

        $newDirective = $extendedSchema->getDirective('new');
        self::assertEquals('new directive', $newDirective->description);
    }

    /**
     * @see it('may extend directives with new complex directive')
     */
    public function testMayExtendDirectivesWithNewComplexDirective()
    {
        $extendedSchema = $this->extendTestSchema('
          directive @profile(enable: Boolean! tag: String) on QUERY | FIELD
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
        self::assertTrue($arg0Type instanceof NonNull);
        self::assertTrue($arg0Type->getWrappedType() instanceof ScalarType);

        self::assertEquals('tag', $arg1->name);
        self::assertTrue($arg1->getType() instanceof ScalarType);
    }

    /**
     * @see it('Rejects invalid SDL')
     */
    public function testRejectsInvalidSDL()
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
    public function testAllowsToDisableSDLValidation()
    {
        $sdl = '
          extend schema @unknown
        ';

        $this->extendTestSchema($sdl, [ 'assumeValid' => true ]);
        $this->extendTestSchema($sdl, [ 'assumeValidSDL' => true ]);
    }

    /**
     * @see it('does not allow replacing a default directive')
     */
    public function testDoesNotAllowReplacingADefaultDirective()
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
     * @see it('does not allow replacing a custom directive')
     */
    public function testDoesNotAllowReplacingACustomDirective()
    {
        $extendedSchema = $this->extendTestSchema('
          directive @meow(if: Boolean!) on FIELD | FRAGMENT_SPREAD
        ');

        $replacementAST = Parser::parse('
            directive @meow(if: Boolean!) on FIELD | QUERY
        ');

        try {
            SchemaExtender::extend($extendedSchema, $replacementAST);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Directive "meow" already exists in the schema. It cannot be redefined.', $error->getMessage());
        }
    }

    /**
     * @see it('does not allow replacing an existing type')
     */
    public function testDoesNotAllowReplacingAnExistingType()
    {
        $existingTypeError = static function ($type) {
            return 'Type "' . $type . '" already exists in the schema. It cannot also be defined in this type definition.';
        };

        $typeSDL = '
            type Bar
        ';

        try {
            $this->extendTestSchema($typeSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('Bar'), $error->getMessage());
        }

        $scalarSDL = '
          scalar SomeScalar
        ';

        try {
            $this->extendTestSchema($scalarSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('SomeScalar'), $error->getMessage());
        }

        $interfaceSDL = '
          interface SomeInterface
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('SomeInterface'), $error->getMessage());
        }

        $enumSDL = '
          enum SomeEnum
        ';

        try {
            $this->extendTestSchema($enumSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('SomeEnum'), $error->getMessage());
        }

        $unionSDL = '
          union SomeUnion
        ';

        try {
            $this->extendTestSchema($unionSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('SomeUnion'), $error->getMessage());
        }

        $inputSDL = '
          input SomeInput
        ';

        try {
            $this->extendTestSchema($inputSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingTypeError('SomeInput'), $error->getMessage());
        }
    }

    /**
     * @see it('does not allow replacing an existing field')
     */
    public function testDoesNotAllowReplacingAnExistingField()
    {
        $existingFieldError = static function (string $type, string $field) {
            return 'Field "' . $type . '.' . $field . '" already exists in the schema. It cannot also be defined in this type extension.';
        };

        $typeSDL = '
          extend type Bar {
            foo: Foo
          }
        ';

        try {
            $this->extendTestSchema($typeSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingFieldError('Bar', 'foo'), $error->getMessage());
        }

        $interfaceSDL = '
          extend interface SomeInterface {
            some: Foo
          }
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            self::fail();
        } catch (Error  $error) {
            self::assertEquals($existingFieldError('SomeInterface', 'some'), $error->getMessage());
        }

        $inputSDL = '
          extend input SomeInput {
            fooArg: String
          }
        ';

        try {
            $this->extendTestSchema($inputSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($existingFieldError('SomeInput', 'fooArg'), $error->getMessage());
        }
    }

    /**
     * @see it('does not allow replacing an existing enum value')
     */
    public function testDoesNotAllowReplacingAnExistingEnumValue()
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

    /**
     * @see it('does not allow referencing an unknown type')
     */
    public function testDoesNotAllowReferencingAnUnknownType()
    {
        $unknownTypeError = 'Unknown type: "Quix". Ensure that this type exists either in the original schema, or is added in a type definition.';

        $typeSDL = '
          extend type Bar {
            quix: Quix
          }
        ';

        try {
            $this->extendTestSchema($typeSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($unknownTypeError, $error->getMessage());
        }

        $interfaceSDL = '
          extend interface SomeInterface {
            quix: Quix
          }
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($unknownTypeError, $error->getMessage());
        }

        $unionSDL = '
          extend union SomeUnion = Quix
        ';

        try {
            $this->extendTestSchema($unionSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($unknownTypeError, $error->getMessage());
        }

        $inputSDL = '
          extend input SomeInput {
            quix: Quix
          }
        ';

        try {
            $this->extendTestSchema($inputSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals($unknownTypeError, $error->getMessage());
        }
    }

    /**
     * @see it('does not allow extending an unknown type')
     */
    public function testDoesNotAllowExtendingAnUnknownType()
    {
        $sdls = [
            'extend scalar UnknownType @foo',
            'extend type UnknownType @foo',
            'extend interface UnknownType @foo',
            'extend enum UnknownType @foo',
            'extend union UnknownType @foo',
            'extend input UnknownType @foo',
        ];

        foreach ($sdls as $sdl) {
            try {
                $this->extendTestSchema($sdl);
                self::fail();
            } catch (Error $error) {
                self::assertEquals('Cannot extend type "UnknownType" because it does not exist in the existing schema.', $error->getMessage());
            }
        }
    }

    /**
     * @see it('does not allow extending a mismatch type')
     */
    public function testDoesNotAllowExtendingAMismatchType()
    {
        $typeSDL = '
          extend type SomeInterface @foo
        ';

        try {
            $this->extendTestSchema($typeSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Cannot extend non-object type "SomeInterface".', $error->getMessage());
        }

        $interfaceSDL = '
          extend interface Foo @foo
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Cannot extend non-interface type "Foo".', $error->getMessage());
        }

        $enumSDL = '
          extend enum Foo @foo
        ';

        try {
            $this->extendTestSchema($enumSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Cannot extend non-enum type "Foo".', $error->getMessage());
        }

        $unionSDL = '
          extend union Foo @foo
        ';

        try {
            $this->extendTestSchema($unionSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Cannot extend non-union type "Foo".', $error->getMessage());
        }

        $inputSDL = '
          extend input Foo @foo
        ';

        try {
            $this->extendTestSchema($inputSDL);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Cannot extend non-input object type "Foo".', $error->getMessage());
        }
    }

    /**
     * @see it('does not automatically include common root type names')
     */
    public function testDoesNotAutomaticallyIncludeCommonRootTypeNames()
    {
        $schema = $this->extendTestSchema('
            type Mutation {
              doSomething: String
            }
        ');

        self::assertNull($schema->getMutationType());
    }

    /**
     * @see it('adds schema definition missing in the original schema')
     */
    public function testAddsSchemaDefinitionMissingInTheOriginalSchema()
    {
        $schema = new Schema([
            'directives' => [$this->FooDirective],
            'types' => [$this->FooType],
        ]);

        self::assertNull($schema->getQueryType());

        $ast = Parser::parse('
            schema @foo {
              query: Foo
            }
        ');

        $schema    = SchemaExtender::extend($schema, $ast);
        $queryType = $schema->getQueryType();

        self::assertEquals($queryType->name, 'Foo');
    }

    /**
     * @see it('adds new root types via schema extension')
     */
    public function testAddsNewRootTypesViaSchemaExtension()
    {
        $schema = $this->extendTestSchema('
            extend schema {
              mutation: Mutation
            }
            type Mutation {
              doSomething: String
            }
        ');

        $mutationType = $schema->getMutationType();
        self::assertEquals($mutationType->name, 'Mutation');
    }

    /**
     * @see it('adds multiple new root types via schema extension')
     */
    public function testAddsMultipleNewRootTypesViaSchemaExtension()
    {
        $schema           = $this->extendTestSchema('
            extend schema {
              mutation: Mutation
              subscription: Subscription
            }
            type Mutation {
              doSomething: String
            }
            type Subscription {
              hearSomething: String
            }
        ');
        $mutationType     = $schema->getMutationType();
        $subscriptionType = $schema->getSubscriptionType();

        self::assertEquals('Mutation', $mutationType->name);
        self::assertEquals('Subscription', $subscriptionType->name);
    }

    /**
     * @see it('applies multiple schema extensions')
     */
    public function testAppliesMultipleSchemaExtensions()
    {
        $schema = $this->extendTestSchema('
            extend schema {
              mutation: Mutation
            }
            extend schema {
              subscription: Subscription
            }
            type Mutation {
              doSomething: String
            }
            type Subscription {
              hearSomething: String
            }
        ');

        $mutationType     = $schema->getMutationType();
        $subscriptionType = $schema->getSubscriptionType();

        self::assertEquals('Mutation', $mutationType->name);
        self::assertEquals('Subscription', $subscriptionType->name);
    }

    /**
     * @see it('schema extension AST are available from schema object')
     */
    public function testSchemaExtensionASTAreAvailableFromSchemaObject()
    {
        $schema = $this->extendTestSchema('
            extend schema {
              mutation: Mutation
            }
            extend schema {
              subscription: Subscription
            }
            type Mutation {
              doSomething: String
            }
            type Subscription {
              hearSomething: String
            }
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
                array_map(static function ($node) {
                    return Printer::doPrint($node) . "\n";
                }, $nodes)
            )
        );
    }

    /**
     * @see it('does not allow redefining an existing root type')
     */
    public function testDoesNotAllowRedefiningAnExistingRootType()
    {
        $sdl = '
            extend schema {
                query: SomeType
            }

            type SomeType {
                seeSomething: String
            }
        ';

        try {
            $this->extendTestSchema($sdl);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Must provide only one query type in schema.', $error->getMessage());
        }
    }

    /**
     * @see it('does not allow defining a root operation type twice')
     */
    public function testDoesNotAllowDefiningARootOperationTypeTwice()
    {
        $sdl = '
            extend schema {
              mutation: Mutation
            }
            extend schema {
              mutation: Mutation
            }
            type Mutation {
              doSomething: String
            }
        ';

        try {
            $this->extendTestSchema($sdl);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Must provide only one mutation type in schema.', $error->getMessage());
        }
    }

    /**
     * @see it('does not allow defining a root operation type with different types')
     */
    public function testDoesNotAllowDefiningARootOperationTypeWithDifferentTypes()
    {
        $sdl = '
            extend schema {
              mutation: Mutation
            }
            extend schema {
              mutation: SomethingElse
            }
            type Mutation {
              doSomething: String
            }
            type SomethingElse {
              doSomethingElse: String
            }
        ';

        try {
            $this->extendTestSchema($sdl);
            self::fail();
        } catch (Error $error) {
            self::assertEquals('Must provide only one mutation type in schema.', $error->getMessage());
        }
    }

    /**
     * @see https://github.com/webonyx/graphql-php/pull/381
     */
    public function testOriginalResolversArePreserved()
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::string(),
                    'resolve' => static function () {
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

        self::assertInternalType('callable', $helloResolveFn);

        $query  = '{ hello }';
        $result = GraphQL::executeQuery($extendedSchema, $query);
        self::assertSame(['data' => ['hello' => 'Hello World!']], $result->toArray());
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/180
     */
    public function testShouldBeAbleToIntroduceNewTypesThroughExtension()
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
}
