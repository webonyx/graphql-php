<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Tests\Utils\SchemaExtenderTest\SomeInterfaceClassType;
use GraphQL\Tests\Utils\SchemaExtenderTest\SomeObjectClassType;
use GraphQL\Tests\Utils\SchemaExtenderTest\SomeScalarClassType;
use GraphQL\Tests\Utils\SchemaExtenderTest\SomeUnionClassType;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaExtender;
use GraphQL\Utils\SchemaPrinter;
use function implode;
use function in_array;
use function iterator_to_array;
use stdClass;

final class SchemaExtenderTest extends TestCaseBase
{
    protected Schema $testSchema;

    /** @var array<int, string> */
    protected array $testSchemaDefinitions;

    protected ObjectType $FooType;

    protected Directive $FooDirective;

    public function setUp(): void
    {
        parent::setUp();

        $SomeScalarType = new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => static fn ($x) => $x,
        ]);

        $SomeInterfaceType = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => static function () use (&$SomeInterfaceType): array {
                return [
                    'some' => ['type' => $SomeInterfaceType],
                ];
            },
        ]);

        $AnotherInterfaceType = new InterfaceType([
            'name' => 'AnotherInterface',
            'interfaces' => [$SomeInterfaceType],
            'fields' => static function () use (&$AnotherInterfaceType): array {
                assert($AnotherInterfaceType instanceof InterfaceType);

                return [
                    'name' => ['type' => Type::string()],
                    'some' => ['type' => $AnotherInterfaceType],
                ];
            },
        ]);

        $FooType = new ObjectType([
            'name' => 'Foo',
            'interfaces' => [$AnotherInterfaceType, $SomeInterfaceType],
            'fields' => static function () use ($AnotherInterfaceType, &$FooType): array {
                assert($FooType instanceof ObjectType);

                return [
                    'name' => ['type' => Type::string()],
                    'some' => ['type' => $AnotherInterfaceType],
                    'tree' => ['type' => Type::nonNull(Type::listOf($FooType))],
                ];
            },
        ]);

        $BarType = new ObjectType([
            'name' => 'Bar',
            'interfaces' => [$SomeInterfaceType],
            'fields' => static fn (): array => [
                'some' => ['type' => $SomeInterfaceType],
                'foo' => ['type' => $FooType],
            ],
        ]);

        $BizType = new ObjectType([
            'name' => 'Biz',
            'fields' => static fn (): array => [
                'fizz' => ['type' => Type::string()],
            ],
        ]);

        $SomeUnionType = new UnionType([
            'name' => 'SomeUnion',
            'types' => [$FooType, $BizType],
        ]);

        $SomeEnumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'ONE' => ['value' => 1],
                'TWO' => ['value' => 2],
            ],
        ]);

        $SomeInputType = new InputObjectType([
            'name' => 'SomeInput',
            'fields' => static fn (): array => [
                'fooArg' => ['type' => Type::string()],
            ],
        ]);

        $FooDirective = new Directive([
            'name' => 'foo',
            'args' => ['input' => $SomeInputType],
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
                'fields' => static fn (): array => [
                    'foo' => ['type' => $FooType],
                    'someScalar' => ['type' => $SomeScalarType],
                    'someUnion' => ['type' => $SomeUnionType],
                    'someEnum' => ['type' => $SomeEnumType],
                    'someInterface' => [
                        'args' => [
                            'id' => [
                                'type' => Type::nonNull(Type::id()),
                            ],
                        ],
                        'type' => $SomeInterfaceType,
                    ],
                    'someInput' => [
                        'args' => [
                            'input' => [
                                'type' => $SomeInputType,
                            ],
                        ],
                        'type' => Type::string(),
                    ],
                ],
            ]),
            'types' => [$FooType, $BarType],
            'directives' => array_merge(GraphQL::getStandardDirectives(), [$FooDirective]),
        ]);

        $testSchemaAst = Parser::parse(SchemaPrinter::doPrint($this->testSchema));

        foreach ($testSchemaAst->definitions as $definition) {
            $this->testSchemaDefinitions[] = Printer::doPrint($definition);
        }

        $this->FooDirective = $FooDirective;
        $this->FooType = $FooType;
    }

    /**
     * @param array<string, bool> $options
     */
    protected function extendTestSchema(string $sdl, array $options = []): Schema
    {
        $originalPrint = SchemaPrinter::doPrint($this->testSchema);

        $ast = Parser::parse($sdl);
        $extendedSchema = SchemaExtender::extend($this->testSchema, $ast, $options);

        self::assertSame(SchemaPrinter::doPrint($this->testSchema), $originalPrint);

        return $extendedSchema;
    }

    protected function printTestSchemaChanges(Schema $extendedSchema): string
    {
        $ast = Parser::parse(SchemaPrinter::doPrint($extendedSchema));
        /** @var array<Node&DefinitionNode> $extraDefinitions */
        $extraDefinitions = array_values(array_filter(
            iterator_to_array($ast->definitions->getIterator()),
            fn (Node $node): bool => ! in_array(Printer::doPrint($node), $this->testSchemaDefinitions, true)
        ));
        /** @phpstan-var NodeList<DefinitionNode&Node> $definitionNodeList */
        $definitionNodeList = new NodeList($extraDefinitions);
        $ast->definitions = $definitionNodeList;

        return Printer::doPrint($ast);
    }

    /**
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

    private static function printSchemaChanges(Schema $schema, Schema $extendedSchema): string
    {
        $schemaDefinitions = self::schemaDefinitions($schema);
        $extendedDefinitions = self::schemaDefinitions($extendedSchema);

        $changed = array_diff($extendedDefinitions, $schemaDefinitions);

        return implode("\n\n", $changed);
    }

    private static function assertSchemaEquals(Schema $expectedSchema, Schema $actualSchema): void
    {
        self::assertSame(
            SchemaPrinter::doPrint($expectedSchema),
            SchemaPrinter::doPrint($actualSchema),
        );
    }

    /**
     * @see it('returns the original schema when there are no type definitions')
     */
    public function testReturnsTheOriginalSchemaWhenThereAreNoTypeDefinitions(): void
    {
        $schema = BuildSchema::build('type Query');
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('{ field }'));
        self::assertSchemaEquals($schema, $extendedSchema);
    }

    /**
     * @see it('can be used for limited execution')
     */
    public function testCanBeUsedForLimitedExecution(): void
    {
        $schema = BuildSchema::build('type Query');
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('
          extend type Query {
            newField: String
          }
        '));

        $result = GraphQL::executeQuery(
            $extendedSchema,
            '{ newField }',
            ['newField' => 123]
        );

        self::assertSame($result->toArray(), [
            'data' => [
                'newField' => '123',
            ],
        ]);
    }

    /**
     * @see it('extends objects by adding new fields')
     */
    public function testExtendsObjectsByAddingNewFields(): void
    {
        $schema = BuildSchema::build('
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

        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('
      extend type SomeObject {
        """New field description."""
        newField(arg: Boolean): String
      }
        '));
        self::assertSame([], $extendedSchema->validate());

        self::assertSame(
            <<<'EOF'
type SomeObject implements AnotherInterface & SomeInterface {
  self: SomeObject
  tree: [SomeObject]!
  """Old field description."""
  oldField: String
  """New field description."""
  newField(arg: Boolean): String
}
EOF,
            self::printSchemaChanges($schema, $extendedSchema),
        );
    }

    /**
     * @see it('extends objects with standard type fields', () => {
     */
    public function testExtendsObjectsWithStandardTypeFields(): void
    {
        // @phpstan-ignore-next-line
        $this->markTestSkipped('See https://github.com/webonyx/graphql-php/issues/964');
    }

    /**
     * @see it('extends enums by adding new values')
     */
    public function testExtendsEnumsByAddingNewValues(): void
    {
        $schema = BuildSchema::build('
      type Query {
        someEnum(arg: SomeEnum): SomeEnum
      }

      directive @foo(arg: SomeEnum) on SCHEMA

      enum SomeEnum {
        """Old value description."""
        OLD_VALUE
      }
        ');
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('
      extend enum SomeEnum {
        """New value description."""
        NEW_VALUE
      }
        '));

        self::assertSame([], $extendedSchema->validate());
        self::assertSame(
            <<<'EOF'
enum SomeEnum {
  """Old value description."""
  OLD_VALUE
  """New value description."""
  NEW_VALUE
}
EOF,
            self::printSchemaChanges($schema, $extendedSchema)
        );
    }

    /**
     * @see it('extends unions by adding new types')
     */
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
        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('
          extend union SomeUnion = Bar
        '));

        self::assertSame([], $schema->validate());
        self::assertSame(
            <<<'EOF'
union SomeUnion = Foo | Biz | Bar
EOF,
            self::printSchemaChanges($schema, $extendedSchema),
        );
    }

    // TODO continue converting tests from here

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

        self::assertSame(
            $this->printTestSchemaChanges($extendedSchema),
            <<<'EOF'
union SomeUnion = Foo | Biz | SomeUnion

EOF
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

        self::assertSame(
            $this->printTestSchemaChanges($extendedSchema),
            <<<'EOF'
input SomeInput {
  fooArg: String
  newField: String
}

EOF
        );

        $queryType = $extendedSchema->getQueryType();
        self::assertInstanceOf(ObjectType::class, $queryType);

        self::assertSame(
            $queryType->getField('someInput')->args[0]->getType(),
            $extendedSchema->getType('SomeInput')
        );

        $fooDirective = $extendedSchema->getDirective('foo');
        self::assertInstanceOf(Directive::class, $fooDirective);
        self::assertSame($fooDirective->args[0]->getType(), $extendedSchema->getType('SomeInput'));
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
        self::assertInstanceOf(ScalarType::class, $someScalar);
        self::assertCount(1, $someScalar->extensionASTNodes);
        self::assertSame(
            'extend scalar SomeScalar @foo',
            Printer::doPrint($someScalar->extensionASTNodes[0]),
        );
    }

    /**
     * @see it('correctly assign AST nodes to new and extended types')
     */
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

      enum TestEnum {
        TEST_VALUE
      }

      input TestInput {
        testInputField: TestEnum
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
        $extendedTwiceSchema = SchemaExtender::extend($extendedSchema, $secondExtensionAST);

        $extendedInOneGoSchema = SchemaExtender::extend(
            $schema,
            AST::concatAST([$firstExtensionAST, $secondExtensionAST])
        );
        self::assertSame(
            SchemaPrinter::doPrint($extendedTwiceSchema),
            SchemaPrinter::doPrint($extendedInOneGoSchema),
        );

        $query = $extendedTwiceSchema->getQueryType();
        self::assertInstanceOf(ObjectType::class, $query);

        $someScalar = $extendedTwiceSchema->getType('SomeScalar');
        self::assertInstanceOf(ScalarType::class, $someScalar);

        $someEnum = $extendedTwiceSchema->getType('SomeEnum');
        self::assertInstanceOf(EnumType::class, $someEnum);

        $someUnion = $extendedTwiceSchema->getType('SomeUnion');
        self::assertInstanceOf(UnionType::class, $someUnion);

        $someInput = $extendedTwiceSchema->getType('SomeInput');
        self::assertInstanceOf(InputObjectType::class, $someInput);

        $someInterface = $extendedTwiceSchema->getType('SomeInterface');
        self::assertInstanceOf(InterfaceType::class, $someInterface);

        $testInput = $extendedTwiceSchema->getType('TestInput');
        self::assertInstanceOf(InputObjectType::class, $testInput);

        $testEnum = $extendedTwiceSchema->getType('TestEnum');
        self::assertInstanceOf(EnumType::class, $testEnum);

        $testUnion = $extendedTwiceSchema->getType('TestUnion');
        self::assertInstanceOf(UnionType::class, $testUnion);

        $testInterface = $extendedTwiceSchema->getType('TestInterface');
        self::assertInstanceOf(InterfaceType::class, $testInterface);

        $testType = $extendedTwiceSchema->getType('TestType');
        self::assertInstanceOf(ObjectType::class, $testType);

        $testDirective = $extendedTwiceSchema->getDirective('test');
        self::assertInstanceOf(Directive::class, $testDirective);

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

        self::assertNotNull($testInput->astNode);
        self::assertNotNull($testEnum->astNode);
        self::assertNotNull($testUnion->astNode);
        self::assertNotNull($testInterface->astNode);
        self::assertNotNull($testType->astNode);
        self::assertNotNull($testDirective->astNode);

        $restoredExtensionAST = new DocumentNode([
            'definitions' => new NodeList(array_merge(
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
            )),
        ]);

        self::assertSame(
            SchemaPrinter::doPrint(SchemaExtender::extend($this->testSchema, $restoredExtensionAST)),
            SchemaPrinter::doPrint($extendedTwiceSchema)
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

        $typeWithDeprecatedField = $extendedSchema->getType('TypeWithDeprecatedField');
        self::assertInstanceOf(ObjectType::class, $typeWithDeprecatedField);

        $deprecatedFieldDef = $typeWithDeprecatedField->getField('newDeprecatedField');

        self::assertTrue($deprecatedFieldDef->isDeprecated());
        self::assertSame('not used anymore', $deprecatedFieldDef->deprecationReason);

        $enumWithDeprecatedValue = $extendedSchema->getType('EnumWithDeprecatedValue');
        self::assertInstanceOf(EnumType::class, $enumWithDeprecatedValue);

        $deprecatedEnumDef = $enumWithDeprecatedValue->getValue('DEPRECATED');
        self::assertInstanceOf(EnumValueDefinition::class, $deprecatedEnumDef);

        self::assertTrue($deprecatedEnumDef->isDeprecated());
        self::assertSame('do not use', $deprecatedEnumDef->deprecationReason);
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

        $fooType = $extendedSchema->getType('Foo');
        self::assertInstanceOf(ObjectType::class, $fooType);

        $deprecatedFieldDef = $fooType->getField('deprecatedField');

        self::assertTrue($deprecatedFieldDef->isDeprecated());
        self::assertSame('not used anymore', $deprecatedFieldDef->deprecationReason);
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

        $someEnumType = $extendedSchema->getType('SomeEnum');
        self::assertInstanceOf(EnumType::class, $someEnumType);

        $deprecatedEnumDef = $someEnumType->getValue('DEPRECATED');
        self::assertInstanceOf(EnumValueDefinition::class, $deprecatedEnumDef);

        self::assertTrue($deprecatedEnumDef->isDeprecated());
        self::assertSame('do not use', $deprecatedEnumDef->deprecationReason);
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
        self::assertSame(
            <<<'EOF'
type Unused {
  someField: String
}

EOF
        ,
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
        self::assertSame(
            <<<'EOF'
enum UnusedEnum {
  SOME
}

EOF
,
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
        self::assertSame(
            <<<'EOF'
input UnusedInput {
  someInput: String
}

EOF
,
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
        self::assertSame(
            <<<'EOF'
type DummyUnionMember {
  someField: String
}

union UnusedUnion = DummyUnionMember

EOF
,
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

        self::assertSame(
            <<<'EOF'
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

EOF
,
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

        self::assertSame(
            <<<'EOF'
type Foo implements AnotherInterface & SomeInterface {
  name: String
  some: AnotherInterface
  tree: [Foo]!
  newField(arg1: SomeEnum!): SomeEnum
}

EOF
,
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

        self::assertSame(
            <<<'EOF'
type Biz implements SomeInterface {
  fizz: String
  name: String
  some: SomeInterface
}

EOF
,
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

        self::assertSame(
            <<<'EOF'
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

EOF
,
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

        self::assertSame(
            <<<'EOF'
type Foo implements AnotherInterface & SomeInterface & NewInterface {
  name: String
  some: AnotherInterface
  tree: [Foo]!
  baz: String
}

interface NewInterface {
  baz: String
}

EOF
,
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

        self::assertSame(
            <<<'EOF'
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

EOF
,
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

        self::assertSame(
            <<<'EOF'
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

EOF
,
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

        self::assertSame(
            <<<'EOF'
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

EOF
,
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

        self::assertSame(
            <<<'EOF'
interface SomeInterface {
  some: SomeInterface
  newField: String
}

EOF
,
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

        self::assertSame(
            <<<'EOF'
interface SomeInterface {
  some: SomeInterface
  newFieldA: Int
  newFieldB(test: Boolean): String
}

EOF
,
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
                'fields' => static fn (): array => [
                    'queryField' => ['type' => Type::string()],
                ],
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => static fn (): array => [
                    'mutationField' => ['type' => Type::string()],
                ],
            ]),
            'subscription' => new ObjectType([
                'name' => 'Subscription',
                'fields' => static fn (): array => [
                    'subscriptionField' => ['type' => Type::string()],
                ],
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

        $originalPrint = SchemaPrinter::doPrint($mutationSchema);
        $extendedSchema = SchemaExtender::extend($mutationSchema, $ast);

        self::assertNotEquals($mutationSchema, $extendedSchema);
        self::assertSame($originalPrint, SchemaPrinter::doPrint($mutationSchema));
        self::assertSame(<<<'EOF'
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

EOF
, SchemaPrinter::doPrint($extendedSchema));
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
        self::assertInstanceOf(Directive::class, $newDirective);
        self::assertSame($newDirective->name, 'neat');
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
        self::assertInstanceOf(Directive::class, $newDirective);
        self::assertSame('new directive', $newDirective->description);
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
        self::assertInstanceOf(Directive::class, $extendedDirective);
        self::assertContains('QUERY', $extendedDirective->locations);
        self::assertContains('FIELD', $extendedDirective->locations);

        $args = $extendedDirective->args;
        self::assertCount(2, $args);

        $arg0 = $args[0];
        $arg1 = $args[1];
        $arg0Type = $arg0->getType();

        self::assertInstanceOf(NonNull::class, $arg0Type);
        self::assertSame('enable', $arg0->name);
        self::assertInstanceOf(ScalarType::class, $arg0Type->getWrappedType());

        self::assertInstanceOf(ScalarType::class, $arg1->getType());
        self::assertSame('tag', $arg1->name);
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
            self::assertSame('Directive "@include" already exists in the schema. It cannot be redefined.', $error->getMessage());
        }
    }

    /**
     * @see it('does not automatically include common root type names')
     */
    public function testDoesNotAutomaticallyIncludeCommonRootTypeNames(): void
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
    public function testAddsSchemaDefinitionMissingInTheOriginalSchema(): void
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

        $schema = SchemaExtender::extend($schema, $ast);
        $queryType = $schema->getQueryType();
        self::assertInstanceOf(ObjectType::class, $queryType);

        self::assertSame($queryType->name, 'Foo');
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
            type Mutation {
              doSomething: String
            }
        ');

        $mutationType = $schema->getMutationType();
        self::assertInstanceOf(ObjectType::class, $mutationType);
        self::assertSame($mutationType->name, 'Mutation');
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
            type Mutation {
              doSomething: String
            }
            type Subscription {
              hearSomething: String
            }
        ');

        $mutationType = $schema->getMutationType();
        self::assertInstanceOf(ObjectType::class, $mutationType);
        self::assertSame('Mutation', $mutationType->name);

        $subscriptionType = $schema->getSubscriptionType();
        self::assertInstanceOf(ObjectType::class, $subscriptionType);
        self::assertSame('Subscription', $subscriptionType->name);
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

        $mutationType = $schema->getMutationType();
        self::assertInstanceOf(ObjectType::class, $mutationType);
        self::assertSame('Mutation', $mutationType->name);

        $subscriptionType = $schema->getSubscriptionType();
        self::assertInstanceOf(ObjectType::class, $subscriptionType);
        self::assertSame('Subscription', $subscriptionType->name);
    }

    /**
     * @see it('schema extension AST are available from schema object')
     */
    public function testSchemaExtensionASTAreAvailableFromSchemaObject(): void
    {
        $schema = BuildSchema::build('
        type Query

        directive @foo on SCHEMA
        ');

        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('
        extend schema {
          mutation: Mutation
        }
        type Mutation

        extend schema {
          subscription: Subscription
        }
        type Subscription
        '));

        $extendedTwiceSchema = SchemaExtender::extend($extendedSchema, Parser::parse('
        extend schema @foo
        '));

        self::assertSame(
            <<<'EOF'
extend schema {
  mutation: Mutation
}

extend schema {
  subscription: Subscription
}

extend schema @foo
EOF
,
            implode(
                "\n\n",
                array_map(
                    [Printer::class, 'doPrint'],
                    $extendedTwiceSchema->extensionASTNodes
                )
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
                    'resolve' => static fn (): string => 'Hello World!',
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
        self::assertInstanceOf(ObjectType::class, $extendedQueryType);

        $helloResolveFn = $extendedQueryType->getField('hello')->resolveFn;
        self::assertIsCallable($helloResolveFn);

        $query = /** @lang GraphQL */ '{ hello }';
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
            'resolveField' => static fn (): string => 'Hello World!',
        ]);

        $schema = new Schema(['query' => $queryType]);

        $documentNode = Parser::parse(/** @lang GraphQL */ '
extend type Query {
	misc: String
}
');

        $extendedSchema = SchemaExtender::extend($schema, $documentNode);
        $extendedQueryType = $extendedSchema->getQueryType();
        self::assertInstanceOf(ObjectType::class, $extendedQueryType);

        $queryResolveFieldFn = $extendedQueryType->resolveFieldFn;
        self::assertIsCallable($queryResolveFieldFn);

        $query = /** @lang GraphQL */ '{ hello }';
        $result = GraphQL::executeQuery($extendedSchema, $query);
        self::assertSame(['data' => ['hello' => 'Hello World!']], $result->toArray());
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/180
     */
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

        static::assertSame(<<<'EOF'
type Bar {
  foo: Foo
}

type Foo {
  value: Int
}

type Query {
  defaultValue: String
}

EOF
        , SchemaPrinter::doPrint($extendedSchema));
    }

    /**
     * @see https://github.com/webonyx/graphql-php/pull/929
     */
    public function testPreservesRepeatableInDirective(): void
    {
        $schema = BuildSchema::build(/** @lang GraphQL */ '
            directive @test(arg: Int) repeatable on FIELD | SCALAR
        ');

        $test = $schema->getDirective('test');
        self::assertInstanceOf(Directive::class, $test);
        self::assertTrue($test->isRepeatable);

        $extendedSchema = SchemaExtender::extend($schema, Parser::parse('scalar Foo'));

        $extendedTest = $extendedSchema->getDirective('test');
        self::assertInstanceOf(Directive::class, $extendedTest);
        self::assertTrue($extendedTest->isRepeatable);
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
            'resolveField' => static fn (): string => 'Hello World!',
        ]);

        $schema = new Schema(['query' => $queryType]);

        $documentNode = Parser::parse(/** @lang GraphQL */ '
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
                    $typeConfig['resolveField'] = static fn (): string => 'bar';
                    break;
            }

            return $typeConfig;
        };

        $extendedSchema = SchemaExtender::extend($schema, $documentNode, [], $typeConfigDecorator);

        $query = /** @lang GraphQL */ '
        {
            hello
            foo {
              value
            }
        }
        ';
        $result = GraphQL::executeQuery($extendedSchema, $query);

        self::assertSame(['data' => ['hello' => 'Hello World!', 'foo' => ['value' => 'bar']]], $result->toArray());
    }

    public function testPreservesScalarClassMethods(): void
    {
        $SomeScalarClassType = new SomeScalarClassType(['serialize' => static fn () => null]);

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
            'resolveField' => static fn (): stdClass => new stdClass(),
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
            'resolveField' => static fn (): stdClass => new stdClass(),
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
