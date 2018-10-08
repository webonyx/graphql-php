<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
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
use const PHP_EOL;

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
            'fields' => static function () use ($SomeInterfaceType, $FooType) {
                return [
                    'name' => [ 'type' => Type::string() ],
                    'some' => [ 'type' => $SomeInterfaceType ],
                    'foo' => [ 'type' => $FooType ],
                ];
            },
        ]);

        $BizType = new ObjectType([
            'name' => 'Biz',
            'fields' => static function () {
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
            'fields' => static function () {
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
                'fields' => static function () use ($FooType, $SomeScalarType, $SomeUnionType, $SomeEnumType, $SomeInterfaceType, $SomeInputType) {
                    return [
                        'foo' => [ 'type' => $FooType ],
                        'someScalar' => [ 'type' => $SomeScalarType ],
                        'someUnion' => [ 'type' => $SomeUnionType ],
                        'someEnum' => [ 'type' => $SomeEnumType ],
                        'someInterface' => [
                            'args' => [
                                'id' => [
                                    'type' => Type::nonNull(Type::ID()),
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
        $this->assertEquals(SchemaPrinter::doPrint($this->testSchema), $originalPrint);
        return $extendedSchema;
    }

    protected function printTestSchemaChanges(Schema $extendedSchema) : string
    {
        $ast              = Parser::parse(SchemaPrinter::doPrint($extendedSchema));
        $ast->definitions = array_values(array_filter(
            iterator_to_array($ast->definitions->getIterator()),
            function ($node) {
                return ! in_array(Printer::doPrint($node), $this->testSchemaDefinitions);
            }
        ));

        return Printer::doPrint($ast);
    }

    /**
     * @see it('returns the original schema when there are no type definitions')
     */
    public function testReturnsTheOriginalSchemaWhenThereAreNoTypeDefinitions()
    {
        $extendedSchema = $this->extendTestSchema('{ field }');
        $this->assertEquals($extendedSchema, $this->testSchema);
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
        $this->assertNotEquals($extendedSchema, $this->testSchema);
        $this->assertContains('newField', SchemaPrinter::doPrint($extendedSchema));
        $this->assertNotContains('newField', SchemaPrinter::doPrint($this->testSchema));
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

        $this->assertEquals($result->toArray(), [
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

        $this->assertEquals(
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

        $this->assertEquals(
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

        $this->assertEquals(
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

        $this->assertEquals(
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
        $this->assertEquals($fooField->getType(), $fooType);
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

        $this->assertEquals(
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
        $this->assertEquals($enumField->getType(), $someEnumType);
    }

    /**
     * @see it('extends unions by adding new types')
     */
    public function testExtendsUnionsByAddingNewTypes()
    {
        $extendedSchema = $this->extendTestSchema('
          extend union SomeUnion = Bar
        ');
        $this->assertEquals(
            $this->printTestSchemaChanges($extendedSchema),
            $this->dedent('
              union SomeUnion = Foo | Biz | Bar
            ')
        );

        $someUnionType = $extendedSchema->getType('SomeUnion');
        $unionField    = $extendedSchema->getQueryType()->getField('someUnion');
        $this->assertEquals($unionField->getType(), $someUnionType);
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
        $this->assertGreaterThan(0, count($errors));

        $this->assertEquals(
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

        $this->assertEquals(
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
        $this->assertEquals($inputField->args[0]->getType(), $someInputType);

        $fooDirective = $extendedSchema->getDirective('foo');
        $this->assertEquals($fooDirective->args[0]->getType(), $someInputType);
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
        $this->assertCount(1, $someScalar->extensionASTNodes);
        $this->assertEquals(
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
        $someScalar          = $extendedTwiceSchema->getType('SomeScalar');
        $someEnum            = $extendedTwiceSchema->getType('SomeEnum');
        $someUnion           = $extendedTwiceSchema->getType('SomeUnion');
        $someInput           = $extendedTwiceSchema->getType('SomeInput');
        $someInterface       = $extendedTwiceSchema->getType('SomeInterface');

        $testInput     = $extendedTwiceSchema->getType('TestInput');
        $testEnum      = $extendedTwiceSchema->getType('TestEnum');
        $testUnion     = $extendedTwiceSchema->getType('TestUnion');
        $testInterface = $extendedTwiceSchema->getType('TestInterface');
        $testType      = $extendedTwiceSchema->getType('TestType');
        $testDirective = $extendedTwiceSchema->getDirective('test');

        $this->assertCount(2, $query->extensionASTNodes);
        $this->assertCount(2, $someScalar->extensionASTNodes);
        $this->assertCount(2, $someEnum->extensionASTNodes);
        $this->assertCount(2, $someUnion->extensionASTNodes);
        $this->assertCount(2, $someInput->extensionASTNodes);
        $this->assertCount(2, $someInterface->extensionASTNodes);

        $this->assertCount(0, $testType->extensionASTNodes ?? []);
        $this->assertCount(0, $testEnum->extensionASTNodes ?? []);
        $this->assertCount(0, $testUnion->extensionASTNodes ?? []);
        $this->assertCount(0, $testInput->extensionASTNodes ?? []);
        $this->assertCount(0, $testInterface->extensionASTNodes ?? []);

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

        $this->assertEquals(
            SchemaPrinter::doPrint(SchemaExtender::extend($this->testSchema, $restoredExtensionAST)),
            SchemaPrinter::doPrint($extendedTwiceSchema)
        );

        $newField = $query->getField('newField');

        $this->assertEquals(Printer::doPrint($newField->astNode), 'newField(testArg: TestInput): TestEnum');
        $this->assertEquals(Printer::doPrint($newField->args[0]->astNode), 'testArg: TestInput');
        $this->assertEquals(Printer::doPrint($query->getField('oneMoreNewField')->astNode), 'oneMoreNewField: TestUnion');
        $this->assertEquals(Printer::doPrint($someEnum->getValue('NEW_VALUE')->astNode), 'NEW_VALUE');
        $this->assertEquals(Printer::doPrint($someEnum->getValue('ONE_MORE_NEW_VALUE')->astNode), 'ONE_MORE_NEW_VALUE');
        $this->assertEquals(Printer::doPrint($someInput->getField('newField')->astNode), 'newField: String');
        $this->assertEquals(Printer::doPrint($someInput->getField('oneMoreNewField')->astNode), 'oneMoreNewField: String');
        $this->assertEquals(Printer::doPrint($someInterface->getField('newField')->astNode), 'newField: String');
        $this->assertEquals(Printer::doPrint($someInterface->getField('oneMoreNewField')->astNode), 'oneMoreNewField: String');
        $this->assertEquals(Printer::doPrint($testInput->getField('testInputField')->astNode), 'testInputField: TestEnum');
        $this->assertEquals(Printer::doPrint($testEnum->getValue('TEST_VALUE')->astNode), 'TEST_VALUE');
        $this->assertEquals(Printer::doPrint($testInterface->getField('interfaceField')->astNode), 'interfaceField: String');
        $this->assertEquals(Printer::doPrint($testType->getField('interfaceField')->astNode), 'interfaceField: String');
        $this->assertEquals(Printer::doPrint($testDirective->args[0]->astNode), 'arg: Int');
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

        $deprecatedFieldDef = $extendedSchema
            ->getType('TypeWithDeprecatedField')
            ->getField('newDeprecatedField');

        $this->assertEquals(true, $deprecatedFieldDef->isDeprecated());
        $this->assertEquals('not used anymore', $deprecatedFieldDef->deprecationReason);

        $deprecatedEnumDef = $extendedSchema
            ->getType('EnumWithDeprecatedValue')
            ->getValue('DEPRECATED');

        $this->assertEquals(true, $deprecatedEnumDef->isDeprecated());
        $this->assertEquals('do not use', $deprecatedEnumDef->deprecationReason);
    }

    /**
     * @see it('extends objects with deprecated fields')
     */
    public function testExtendsObjectsWithDeprecatedFields()
    {
        $extendedSchema     = $this->extendTestSchema('
          extend type Foo {
            deprecatedField: String @deprecated(reason: "not used anymore")
          }
        ');
        $deprecatedFieldDef = $extendedSchema->getType('Foo')->getField('deprecatedField');
        $this->assertTrue($deprecatedFieldDef->isDeprecated());
        $this->assertEquals('not used anymore', $deprecatedFieldDef->deprecationReason);
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

        $deprecatedEnumDef = $extendedSchema
            ->getType('SomeEnum')
            ->getValue('DEPRECATED');

        $this->assertTrue($deprecatedEnumDef->isDeprecated());
        $this->assertEquals('do not use', $deprecatedEnumDef->deprecationReason);
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
        $this->assertNotEquals($this->testSchema, $extendedSchema);
        $this->assertEquals(
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
        $this->assertNotEquals($extendedSchema, $this->testSchema);
        $this->assertEquals(
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

        $this->assertNotEquals($extendedSchema, $this->testSchema);
        $this->assertEquals(
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

        $this->assertNotEquals($extendedSchema, $this->testSchema);
        $this->assertEquals(
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

        $this->assertEquals(
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

        $this->assertEquals(
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

        $this->assertEquals(
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

        $this->assertEquals(
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

        $this->assertEquals(
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

        $this->assertEquals(
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

        $this->assertEquals(
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
        $this->assertGreaterThan(0, $errors);

        $this->assertEquals(
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

        $this->assertEquals(
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
        $this->assertNotEquals($mutationSchema, $extendedSchema);
        $this->assertEquals(SchemaPrinter::doPrint($mutationSchema), $originalPrint);
        $this->assertEquals(SchemaPrinter::doPrint($extendedSchema), $this->dedent('
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
        $this->assertEquals($newDirective->name, 'neat');
        $this->assertContains('QUERY', $newDirective->locations);
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
        $this->assertEquals('new directive', $newDirective->description);
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
        $this->assertEquals('new directive', $newDirective->description);
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
        $this->assertContains('QUERY', $extendedDirective->locations);
        $this->assertContains('FIELD', $extendedDirective->locations);

        $args = $extendedDirective->args;
        $this->assertCount(2, $args);

        $arg0 = $args[0];
        $arg1 = $args[1];

        $this->assertEquals('enable', $arg0->name);
        $this->assertTrue($arg0->getType() instanceof NonNull);
        $this->assertTrue($arg0->getType()->getWrappedType() instanceof ScalarType);

        $this->assertEquals('tag', $arg1->name);
        $this->assertTrue($arg1->getType() instanceof ScalarType);
    }

    /**
     * @see it('Rejects invalid SDL')
     */
    public function testRejectsInvalidSDL()
    {
        $sdl = '
            extend schema @unknown
        ';

        try {
            $this->extendTestSchema($sdl);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Unknown directive "unknown".', $error->getMessage());
        }
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
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Directive "include" already exists in the schema. It cannot be redefined.', $error->getMessage());
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
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Directive "meow" already exists in the schema. It cannot be redefined.', $error->getMessage());
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
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($existingTypeError('Bar'), $error->getMessage());
        }

        $scalarSDL = '
          scalar SomeScalar
        ';

        try {
            $this->extendTestSchema($scalarSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($existingTypeError('SomeScalar'), $error->getMessage());
        }

        $interfaceSDL = '
          interface SomeInterface
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($existingTypeError('SomeInterface'), $error->getMessage());
        }

        $enumSDL = '
          enum SomeEnum
        ';

        try {
            $this->extendTestSchema($enumSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($existingTypeError('SomeEnum'), $error->getMessage());
        }

        $unionSDL = '
          union SomeUnion
        ';

        try {
            $this->extendTestSchema($unionSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($existingTypeError('SomeUnion'), $error->getMessage());
        }

        $inputSDL = '
          input SomeInput
        ';

        try {
            $this->extendTestSchema($inputSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($existingTypeError('SomeInput'), $error->getMessage());
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
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($existingFieldError('Bar', 'foo'), $error->getMessage());
        }

        $interfaceSDL = '
          extend interface SomeInterface {
            some: Foo
          }
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            $this->fail();
        } catch (Error  $error) {
            $this->assertEquals($existingFieldError('SomeInterface', 'some'), $error->getMessage());
        }

        $inputSDL = '
          extend input SomeInput {
            fooArg: String
          }
        ';

        try {
            $this->extendTestSchema($inputSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($existingFieldError('SomeInput', 'fooArg'), $error->getMessage());
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
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Enum value "SomeEnum.ONE" already exists in the schema. It cannot also be defined in this type extension.', $error->getMessage());
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
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($unknownTypeError, $error->getMessage());
        }

        $interfaceSDL = '
          extend interface SomeInterface {
            quix: Quix
          }
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($unknownTypeError, $error->getMessage());
        }

        $unionSDL = '
          extend union SomeUnion = Quix
        ';

        try {
            $this->extendTestSchema($unionSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($unknownTypeError, $error->getMessage());
        }

        $inputSDL = '
          extend input SomeInput {
            quix: Quix
          }
        ';

        try {
            $this->extendTestSchema($inputSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals($unknownTypeError, $error->getMessage());
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
                $this->fail();
            } catch (Error $error) {
                $this->assertEquals('Cannot extend type "UnknownType" because it does not exist in the existing schema.', $error->getMessage());
            }
        }
    }

    /**
     * @see it('maintains configuration of the original schema object')
     */
    public function testMaintainsConfigurationOfTheOriginalSchemaObject()
    {
        $this->markTestSkipped('allowedLegacyNames currently not supported');

        $testSchemaWithLegacyNames = new Schema(
            [
                'query' => new ObjectType([
                    'name' => 'Query',
                    'fields' => static function () {
                        return ['id' => ['type' => Type::ID()]];
                    },
                ]),
            ],
            [ 'allowedLegacyNames' => ['__badName'] ]
        );

        $ast    = Parser::parse('
            extend type Query {
                __badName: String
            }
        ');
        $schema = SchemaExtender::extend($testSchemaWithLegacyNames, $ast);
        $this->assertEquals(['__badName'], $schema->__allowedLegacyNames);
    }

    /**
     * @see it('adds to the configuration of the original schema object')
     */
    public function testAddsToTheConfigurationOfTheOriginalSchemaObject()
    {
        $this->markTestSkipped('allowedLegacyNames currently not supported');

        $testSchemaWithLegacyNames = new Schema(
            [
                'query' => new ObjectType([
                    'name' => 'Query',
                    'fields' => static function () {
                        return ['__badName' => ['type' => Type::string()]];
                    },
                ]),
            ],
            ['allowedLegacyNames' => ['__badName']]
        );

        $ast = Parser::parse('
          extend type Query {
            __anotherBadName: String
          }
        ');

        $schema = SchemaExtender::extend($testSchemaWithLegacyNames, $ast, [
            'allowedLegacyNames' => ['__anotherBadName'],
        ]);

        $this->assertEquals(['__badName', '__anotherBadName'], $schema->__allowedLegacyNames);
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
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Cannot extend non-object type "SomeInterface".', $error->getMessage());
        }

        $interfaceSDL = '
          extend interface Foo @foo
        ';

        try {
            $this->extendTestSchema($interfaceSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Cannot extend non-interface type "Foo".', $error->getMessage());
        }

        $enumSDL = '
          extend enum Foo @foo
        ';

        try {
            $this->extendTestSchema($enumSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Cannot extend non-enum type "Foo".', $error->getMessage());
        }

        $unionSDL = '
          extend union Foo @foo
        ';

        try {
            $this->extendTestSchema($unionSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Cannot extend non-union type "Foo".', $error->getMessage());
        }

        $inputSDL = '
          extend input Foo @foo
        ';

        try {
            $this->extendTestSchema($inputSDL);
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Cannot extend non-input object type "Foo".', $error->getMessage());
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

        $this->assertNull($schema->getMutationType());
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

        $this->assertNull($schema->getQueryType());

        $ast = Parser::parse('
            schema @foo {
              query: Foo
            }
        ');

        $schema    = SchemaExtender::extend($schema, $ast);
        $queryType = $schema->getQueryType();

        $this->assertEquals($queryType->name, 'Foo');
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
        $this->assertEquals($mutationType->name, 'Mutation');
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

        $this->assertEquals('Mutation', $mutationType->name);
        $this->assertEquals('Subscription', $subscriptionType->name);
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

        $this->assertEquals('Mutation', $mutationType->name);
        $this->assertEquals('Subscription', $subscriptionType->name);
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
        $this->assertEquals(
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
                PHP_EOL,
                array_map(static function ($node) {
                    return Printer::doPrint($node) . PHP_EOL;
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
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Must provide only one query type in schema.', $error->getMessage());
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
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Must provide only one mutation type in schema.', $error->getMessage());
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
            $this->fail();
        } catch (Error $error) {
            $this->assertEquals('Must provide only one mutation type in schema.', $error->getMessage());
        }
    }
}
