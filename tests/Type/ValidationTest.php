<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaExtender;
use GraphQL\Utils\Utils;

final class ValidationTest extends TestCaseBase
{
    public ScalarType $SomeScalarType;

    public ObjectType $SomeObjectType;

    public UnionType $SomeUnionType;

    public InterfaceType $SomeInterfaceType;

    public EnumType $SomeEnumType;

    public InputObjectType $SomeInputObjectType;

    /** @var array<Type> */
    public array $outputTypes;

    /** @var array<Type> */
    public array $notOutputTypes;

    /** @var array<Type> */
    public array $inputTypes;

    /** @var array<Type> */
    public array $notInputTypes;

    public float $Number;

    public function setUp(): void
    {
        $this->Number = 1;

        $this->SomeScalarType = new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => static function (): void {},
            'parseValue' => static function (): void {},
            'parseLiteral' => static function (): void {},
        ]);

        $this->SomeInterfaceType = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => fn (): array => [
                'f' => [
                    'type' => $this->SomeObjectType,
                ],
            ],
        ]);

        $this->SomeObjectType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => fn (): array => [
                'f' => [
                    'type' => $this->SomeObjectType,
                ],
            ],
            'interfaces' => fn (): array => [$this->SomeInterfaceType],
        ]);

        $this->SomeUnionType = new UnionType([
            'name' => 'SomeUnion',
            'types' => [$this->SomeObjectType],
        ]);

        $this->SomeEnumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'ONLY' => [],
            ],
        ]);

        $this->SomeInputObjectType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'val' => ['type' => Type::string(), 'defaultValue' => 'hello'],
            ],
        ]);

        $this->outputTypes = $this->withModifiers([
            Type::string(),
            $this->SomeScalarType,
            $this->SomeEnumType,
            $this->SomeObjectType,
            $this->SomeUnionType,
            $this->SomeInterfaceType,
        ]);

        $this->notOutputTypes = $this->withModifiers([
            $this->SomeInputObjectType,
        ]);

        $this->inputTypes = $this->withModifiers([
            Type::string(),
            $this->SomeScalarType,
            $this->SomeEnumType,
            $this->SomeInputObjectType,
        ]);

        $this->notInputTypes = $this->withModifiers([
            $this->SomeObjectType,
            $this->SomeUnionType,
            $this->SomeInterfaceType,
        ]);

        Warning::suppress(Warning::WARNING_NOT_A_TYPE);
    }

    /**
     * @param array<Type> $types
     *
     * @return array<Type>
     */
    private function withModifiers(array $types): array
    {
        return array_merge(
            $types,
            array_map(
                static fn (Type $type): ListOfType => Type::listOf($type),
                $types
            ),
            array_map(
                static function (Type $type): NonNull {
                    /** @var Type&NullableType $type */
                    return Type::nonNull($type);
                },
                $types
            ),
            array_map(
                static fn (Type $type): NonNull => Type::nonNull(Type::listOf($type)),
                $types
            )
        );
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Warning::enable(Warning::WARNING_NOT_A_TYPE);
    }

    public function testRejectsTypesWithoutNames(): void
    {
        $this->assertEachCallableThrows(
            [
                // @phpstan-ignore-next-line intentionally wrong
                static fn (): ObjectType => new ObjectType([]),
                // @phpstan-ignore-next-line intentionally wrong
                static fn (): EnumType => new EnumType([]),
                // @phpstan-ignore-next-line intentionally wrong
                static fn (): InputObjectType => new InputObjectType([]),
                // @phpstan-ignore-next-line intentionally wrong
                static fn (): UnionType => new UnionType([]),
                // @phpstan-ignore-next-line intentionally wrong
                static fn (): InterfaceType => new InterfaceType([]),
                static fn (): ScalarType => new CustomScalarType(['parseValue' => static fn ($value) => $value]),
            ],
            'Must provide name for Type.'
        );
    }

    /** @param array<int, \Closure(): Type> $closures */
    private function assertEachCallableThrows(array $closures, string $expectedError): void
    {
        foreach ($closures as $index => $factory) {
            try {
                $factory();
                self::fail('Expected exception not thrown for entry ' . $index);
            } catch (InvariantViolation $e) {
                self::assertSame($expectedError, $e->getMessage(), 'Error in callable #' . $index);
            }
        }
    }

    /*
     * @see describe('Type System: A Schema must have Object root types')
     */

    /** @see it('accepts a Schema whose query type is an object type') */
    public function testAcceptsASchemaWhoseQueryTypeIsAnObjectType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: String
      }
        ');
        self::assertSame([], $schema->validate());

        $schemaWithDef = BuildSchema::build('
      schema {
        query: QueryRoot
      }
      type QueryRoot {
        test: String
      }
    ');
        self::assertSame([], $schemaWithDef->validate());
    }

    /** @see it('accepts a Schema whose query and mutation types are object types') */
    public function testAcceptsASchemaWhoseQueryAndMutationTypesAreObjectTypes(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: String
      }

      type Mutation {
        test: String
      }
        ');
        self::assertSame([], $schema->validate());

        $schema = BuildSchema::build('
      schema {
        query: QueryRoot
        mutation: MutationRoot
      }

      type QueryRoot {
        test: String
      }

      type MutationRoot {
        test: String
      }
        ');
        self::assertSame([], $schema->validate());
    }

    /** @see it('accepts a Schema whose query and subscription types are object types') */
    public function testAcceptsASchemaWhoseQueryAndSubscriptionTypesAreObjectTypes(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: String
      }

      type Subscription {
        test: String
      }
        ');
        self::assertSame([], $schema->validate());

        $schema = BuildSchema::build('
      schema {
        query: QueryRoot
        subscription: SubscriptionRoot
      }

      type QueryRoot {
        test: String
      }

      type SubscriptionRoot {
        test: String
      }
        ');
        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects a Schema without a query type') */
    public function testRejectsASchemaWithoutAQueryType(): void
    {
        $schema = BuildSchema::build('
      type Mutation {
        test: String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [['message' => 'Query root type must be provided.']]
        );

        $schemaWithDef = BuildSchema::build('
      schema {
        mutation: MutationRoot
      }

      type MutationRoot {
        test: String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schemaWithDef->validate(),
            [
                [
                    'message' => 'Query root type must be provided.',
                    'locations' => [['line' => 2, 'column' => 7]],
                ],
            ]
        );
    }

    /** @return array<int, array{line: int, column: int}> */
    private function formatLocations(Error $error): array
    {
        return array_map(
            static fn (SourceLocation $loc): array => [
                'line' => $loc->line,
                'column' => $loc->column,
            ],
            $error->getLocations()
        );
    }

    /**
     * @param array<Error> $errors
     *
     * @return array<int, array{message: string, locations?: array<int, array{line: int, column: int}>}>
     */
    private function formatErrors(array $errors, bool $withLocation = true): array
    {
        return array_map(
            fn (Error $error): array => $withLocation
                ? [
                    'message' => $error->getMessage(),
                    'locations' => $this->formatLocations($error),
                ]
                : [
                    'message' => $error->getMessage(),
                ],
            $errors
        );
    }

    /**
     * @param array<int, Error> $errors
     * @param array<int, array{message: string, locations?: array<int, array{line: int, column: int}>}> $expected
     */
    private function assertMatchesValidationMessage(array $errors, array $expected): void
    {
        /** @var array<int, array{message: string, locations: array<int, array{line: int, column: int}>}> $expectedWithLocations */
        $expectedWithLocations = [];
        foreach ($expected as $index => $err) {
            if (! isset($err['locations']) && isset($errors[$index])) {
                $expectedWithLocations[$index] = $err + ['locations' => $this->formatLocations($errors[$index])];
            } else {
                $expectedWithLocations[$index] = $err;
            }
        }

        self::assertEquals($expectedWithLocations, $this->formatErrors($errors));
    }

    /** @dataProvider rootTypes */
    public function testRejectsASchemaWhoseRootTypeIsAnInputType(string $rootType): void
    {
        $ucfirstRootType = ucfirst($rootType);

        $this->expectRootTypeMustBeObjectTypeNotInputType();
        BuildSchema::build("
      input {$ucfirstRootType} {
        test: String
      }
        ");
    }

    /** @dataProvider rootTypes */
    public function testRejectsASchemaWhoseNonStandardRootTypeIsAnInputType(string $rootType): void
    {
        $this->expectRootTypeMustBeObjectTypeNotInputType();

        BuildSchema::build("
      schema {
        {$rootType}: SomeInputObject
      }

      input SomeInputObject {
        test: String
      }
        ");
    }

    /**
     * @see it('rejects a schema extended with invalid root types')
     *
     * @dataProvider rootTypes
     */
    public function testRejectsASchemaExtendedWithInvalidRootTypes(string $rootType): void
    {
        $schema = BuildSchema::build('
            input SomeInputObject {
                test: String
            }
        ');

        $documentNode = Parser::parse("
            extend schema {
              {$rootType}: SomeInputObject
            }
        ");

        $this->expectRootTypeMustBeObjectTypeNotInputType();
        SchemaExtender::extend($schema, $documentNode);
    }

    /** @return iterable<array{string}> */
    public static function rootTypes(): iterable
    {
        yield ['query'];
        yield ['mutation'];
        yield ['subscription'];
    }

    private function expectRootTypeMustBeObjectTypeNotInputType(): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage("Expected instanceof GraphQL\Type\Definition\ObjectType, a callable that returns such an instance, or null, got: GraphQL\Type\Definition\InputObjectType.");
    }

    /** @see it('rejects a Schema whose directives are incorrectly typed') */
    public function testRejectsASchemaWhoseDirectivesAreIncorrectlyTyped(): void
    {
        // @phpstan-ignore-next-line intentionally wrong
        $schema = new Schema([
            'query' => $this->SomeObjectType,
            'directives' => ['somedirective'],
        ]);

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [['message' => 'Expected directive but got: "somedirective".']]
        );
    }

    /** @see it('accepts an Object type with fields object') */
    public function testAcceptsAnObjectTypeWithFieldsObject(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field: SomeObject
      }

      type SomeObject {
        field: String
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects an Object type with missing fields') */
    public function testRejectsAnObjectTypeWithMissingFields(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: IncompleteObject
      }

      type IncompleteObject
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Type IncompleteObject must define one or more fields.',
                    'locations' => [['line' => 6, 'column' => 7]],
                ],
            ]
        );

        $manualSchema = $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'IncompleteObject',
                'fields' => [],
            ])
        );

        $this->assertMatchesValidationMessage(
            $manualSchema->validate(),
            [['message' => 'Type IncompleteObject must define one or more fields.']]
        );

        $manualSchema2 = $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'IncompleteObject',
                'fields' => static fn (): array => [],
            ])
        );

        $this->assertMatchesValidationMessage(
            $manualSchema2->validate(),
            [['message' => 'Type IncompleteObject must define one or more fields.']]
        );
    }

    /**
     * DESCRIBE: Type System: Fields args must be properly named.
     *
     * @param Type&NamedType $type
     *
     * @throws InvariantViolation
     */
    private function schemaWithFieldType(Type $type): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['f' => ['type' => $type]],
            ]),
            'types' => [$type],
        ]);
    }

    /** @see it('rejects an Object type with incorrectly named fields') */
    public function testRejectsAnObjectTypeWithIncorrectlyNamedFields(): void
    {
        $schema = $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'SomeObject',
                'fields' => [
                    'bad-name-with-dashes' => ['type' => Type::string()],
                ],
            ])
        );

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "bad-name-with-dashes" does not.',
                ],
            ]
        );
    }

    /** DESCRIBE: Type System: Union types must be valid. */
    public function testAcceptsShorthandNotationForFields(): void
    {
        $schema = $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'SomeObject',
                'fields' => [
                    'field' => Type::string(),
                ],
            ])
        );
        $schema->assertValid();
        $this->assertDidNotCrash();
    }

    /** @see it('accepts field args with valid names') */
    public function testAcceptsFieldArgsWithValidNames(): void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'goodField' => [
                    'type' => Type::string(),
                    'args' => [
                        'goodArg' => ['type' => Type::string()],
                    ],
                ],
            ],
        ]));
        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects field arg with invalid names') */
    public function testRejectsFieldArgWithInvalidNames(): void
    {
        $QueryType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'badField' => [
                    'type' => Type::string(),
                    'args' => [
                        'bad-name-with-dashes' => ['type' => Type::string()],
                    ],
                ],
            ],
        ]);
        $schema = new Schema(['query' => $QueryType]);

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [['message' => 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "bad-name-with-dashes" does not.']]
        );
    }

    /** @see it('accepts a Union type with member types') */
    public function testAcceptsAUnionTypeWithArrayTypes(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: GoodUnion
      }

      type TypeA {
        field: String
      }

      type TypeB {
        field: String
      }

      union GoodUnion =
        | TypeA
        | TypeB
        ');

        self::assertSame([], $schema->validate());
    }

    // DESCRIBE: Type System: Input Objects must have fields

    /** @see it('rejects a Union type with empty types') */
    public function testRejectsAUnionTypeWithEmptyTypes(): void
    {
        $schema = BuildSchema::build('
            type Query {
                test: BadUnion
            }
            
            union BadUnion
        ');

        $schema = SchemaExtender::extend(
            $schema,
            Parser::parse('
                directive @test on UNION
        
                extend union BadUnion @test
            ')
        );

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Union type BadUnion must define one or more member types.',
                    'locations' => [['line' => 6, 'column' => 13], ['line' => 4, 'column' => 11]],
                ],
            ]
        );
    }

    /** @see it('rejects a Union type with duplicated member type') */
    public function testRejectsAUnionTypeWithDuplicatedMemberType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: BadUnion
      }

      type TypeA {
        field: String
      }

      type TypeB {
        field: String
      }

      union BadUnion =
        | TypeA
        | TypeB
        | TypeA
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Union type BadUnion can only include type TypeA once.',
                    'locations' => [['line' => 15, 'column' => 11], ['line' => 17, 'column' => 11]],
                ],
            ]
        );

        $extendedSchema = SchemaExtender::extend(
            $schema,
            Parser::parse('extend union BadUnion = TypeB')
        );

        $this->assertMatchesValidationMessage(
            $extendedSchema->validate(),
            [
                [
                    'message' => 'Union type BadUnion can only include type TypeA once.',
                    'locations' => [['line' => 15, 'column' => 11], ['line' => 17, 'column' => 11]],
                ],
                [
                    'message' => 'Union type BadUnion can only include type TypeB once.',
                    'locations' => [['line' => 16, 'column' => 11], ['line' => 3, 'column' => 5]],
                ],
            ]
        );
    }

    /** @see it('rejects a Union type with non-Object members types') */
    public function testRejectsAUnionTypeWithNonObjectMembersType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: BadUnion
      }

      type TypeA {
        field: String
      }

      type TypeB {
        field: String
      }

      union BadUnion =
        | TypeA
        | String
        | TypeB
        ');

        $schema = SchemaExtender::extend(
            $schema,
            Parser::parse('extend union BadUnion = Int')
        );

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Union type BadUnion can only include Object types, it cannot include String.',
                    'locations' => [['line' => 16, 'column' => 11]],
                ],
                [
                    'message' => 'Union type BadUnion can only include Object types, it cannot include Int.',
                    'locations' => [['line' => 1, 'column' => 25]],
                ],
            ]
        );

        $badUnionMemberTypes = [
            Type::string(),
            Type::nonNull($this->SomeObjectType),
            Type::listOf($this->SomeObjectType),
            $this->SomeInterfaceType,
            $this->SomeUnionType,
            $this->SomeEnumType,
            $this->SomeInputObjectType,
        ];

        foreach ($badUnionMemberTypes as $memberType) {
            $badSchema = $this->schemaWithFieldType(
                // @phpstan-ignore-next-line intentionally wrong
                new UnionType([
                    'name' => 'BadUnion',
                    'types' => [$memberType],
                ])
            );
            $notOutputType = Utils::printSafe($memberType);
            $this->assertMatchesValidationMessage(
                $badSchema->validate(),
                [
                    [
                        'message' => "Union type BadUnion can only include Object types, it cannot include {$notOutputType}.",
                    ],
                ]
            );
        }
    }

    // DESCRIBE: Type System: Enum types must be well defined

    /** @see it('accepts an Input Object type with fields') */
    public function testAcceptsAnInputObjectTypeWithFields(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field(arg: SomeInputObject): String
      }

      input SomeInputObject {
        field: String
      }
        ');
        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects an Input Object type with missing fields') */
    public function testRejectsAnInputObjectTypeWithMissingFields(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field(arg: SomeInputObject): String
      }

      input SomeInputObject
        ');

        $schema = SchemaExtender::extend(
            $schema,
            Parser::parse('
        directive @test on INPUT_OBJECT

        extend input SomeInputObject @test
            ')
        );

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Input Object type SomeInputObject must define one or more fields.',
                    'locations' => [['line' => 6, 'column' => 7], ['line' => 3, 'column' => 31]],
                ],
            ]
        );
    }

    /** @see it('accepts an Input Object with breakable circular reference') */
    public function testAcceptsAnInputObjectWithBreakableCircularReference(): void
    {
        $schema = BuildSchema::build('
      input AnotherInputObject {
        parent: SomeInputObject
      }
      
      type Query {
        field(arg: SomeInputObject): String
      }
      
      input SomeInputObject {
        self: SomeInputObject
        arrayOfSelf: [SomeInputObject]
        nonNullArrayOfSelf: [SomeInputObject]!
        nonNullArrayOfNonNullSelf: [SomeInputObject!]!
        intermediateSelf: AnotherInputObject
      }
        ');
        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects an Input Object with non-breakable circular reference') */
    public function testRejectsAnInputObjectWithNonBreakableCircularReference(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field(arg: SomeInputObject): String
      }
      
      input SomeInputObject {
        nonNullSelf: SomeInputObject!
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Cannot reference Input Object "SomeInputObject" within itself through a series of non-null fields: "nonNullSelf".',
                    'locations' => [['line' => 7, 'column' => 9]],
                ],
            ]
        );
    }

    /** @see it('rejects Input Objects with non-breakable circular reference spread across them') */
    public function testRejectsInputObjectsWithNonBreakableCircularReferenceSpreadAcrossThem(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field(arg: SomeInputObject): String
      }
      
      input SomeInputObject {
        startLoop: AnotherInputObject!
      }
      
      input AnotherInputObject {
        nextInLoop: YetAnotherInputObject!
      }
      
      input YetAnotherInputObject {
        closeLoop: SomeInputObject!
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Cannot reference Input Object "SomeInputObject" within itself through a series of non-null fields: "startLoop.nextInLoop.closeLoop".',
                    'locations' => [
                        ['line' => 7, 'column' => 9],
                        ['line' => 11, 'column' => 9],
                        ['line' => 15, 'column' => 9],
                    ],
                ],
            ]
        );
    }

    /** @see it('rejects Input Objects with multiple non-breakable circular reference') */
    public function testRejectsInputObjectsWithMultipleNonBreakableCircularReferences(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field(arg: SomeInputObject): String
      }
      
      input SomeInputObject {
        startLoop: AnotherInputObject!
      }
      
      input AnotherInputObject {
        closeLoop: SomeInputObject!
        startSecondLoop: YetAnotherInputObject!
      }
      
      input YetAnotherInputObject {
        closeSecondLoop: AnotherInputObject!
        nonNullSelf: YetAnotherInputObject!
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Cannot reference Input Object "SomeInputObject" within itself through a series of non-null fields: "startLoop.closeLoop".',
                    'locations' => [
                        ['line' => 7, 'column' => 9],
                        ['line' => 11, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Cannot reference Input Object "AnotherInputObject" within itself through a series of non-null fields: "startSecondLoop.closeSecondLoop".',
                    'locations' => [
                        ['line' => 12, 'column' => 9],
                        ['line' => 16, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Cannot reference Input Object "YetAnotherInputObject" within itself through a series of non-null fields: "nonNullSelf".',
                    'locations' => [
                        ['line' => 17, 'column' => 9],
                    ],
                ],
            ]
        );
    }

    /** @see it('rejects an Input Object type with incorrectly typed fields') */
    public function testRejectsAnInputObjectTypeWithIncorrectlyTypedFields(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field(arg: SomeInputObject): String
      }
      
      type SomeObject {
        field: String
      }

      union SomeUnion = SomeObject
      
      input SomeInputObject {
        badObject: SomeObject
        badUnion: SomeUnion
        goodInputObject: SomeInputObject
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'The type of SomeInputObject.badObject must be Input Type but got: SomeObject.',
                    'locations' => [['line' => 13, 'column' => 20]],
                ],
                [
                    'message' => 'The type of SomeInputObject.badUnion must be Input Type but got: SomeUnion.',
                    'locations' => [['line' => 14, 'column' => 19]],
                ],
            ]
        );
    }

    /** @see it('rejects an Input Object type with required argument that is deprecated' */
    public function testRejectsAnInputObjectTypeWithRequiredArgumentThatIsDeprecated(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field(arg: SomeInputObject): String
      }

      input SomeInputObject {
        optionalField: String @deprecated
        anotherOptionalField: String! = "" @deprecated
        badField: String! @deprecated
      }
        ');

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Required input field SomeInputObject.badField cannot be deprecated.');
        $schema->assertValid();
    }

    /** @see it('rejects an Enum type without values') */
    public function testRejectsAnEnumTypeWithoutValues(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field: SomeEnum
      }
      
      enum SomeEnum
        ');

        $schema = SchemaExtender::extend(
            $schema,
            Parser::parse('
        directive @test on ENUM

        extend enum SomeEnum @test
            ')
        );

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Enum type SomeEnum must define one or more values.',
                    'locations' => [['line' => 6, 'column' => 7], ['line' => 3, 'column' => 23]],
                ],
            ]
        );
    }

    /**
     * DESCRIBE: Type System: Object fields must have output types.
     *
     * @return iterable<array{0: string, 1: string}>
     */
    public static function invalidEnumValueName(): iterable
    {
        yield ['#value', 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "#value" does not.'];
        yield ['1value', 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "1value" does not.'];
        yield ['KEBAB-CASE', 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "KEBAB-CASE" does not.'];
        yield ['false', 'Enum type SomeEnum cannot include value: false.'];
        yield ['true', 'Enum type SomeEnum cannot include value: true.'];
        yield ['null', 'Enum type SomeEnum cannot include value: null.'];
    }

    /**
     * @see          it('rejects an Enum type with incorrectly named values')
     *
     * @dataProvider invalidEnumValueName
     */
    public function testRejectsAnEnumTypeWithIncorrectlyNamedValues(string $name, string $expectedMessage): void
    {
        $schema = $this->schemaWithEnum($name);

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                ['message' => $expectedMessage],
            ]
        );
    }

    /** @throws InvariantViolation */
    private function schemaWithEnum(string $name): Schema
    {
        return $this->schemaWithFieldType(
            new EnumType([
                'name' => 'SomeEnum',
                'values' => [
                    $name => [],
                ],
            ])
        );
    }

    /** @see it('accepts an output type as an Object field type') */
    public function testAcceptsAnOutputTypeAsNnObjectFieldType(): void
    {
        foreach ($this->outputTypes as $type) {
            $schema = $this->schemaWithObjectFieldOfType($type);
            self::assertSame([], $schema->validate());
        }
    }

    /**
     * DESCRIBE: Type System: Objects can only implement unique interfaces.
     *
     * @throws InvariantViolation
     */
    private function schemaWithObjectFieldOfType(Type $fieldType): Schema
    {
        $BadObjectType = new ObjectType([
            'name' => 'BadObject',
            'fields' => [
                'badField' => ['type' => $fieldType],
            ],
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadObjectType],
                ],
            ]),
            'types' => [$this->SomeObjectType],
        ]);
    }

    /** @see it('rejects a non-output type as an Object field type') */
    public function testRejectsANonOutputTypeAsAnObjectFieldType(): void
    {
        foreach ($this->notOutputTypes as $type) {
            $schema = $this->schemaWithObjectFieldOfType($type);
            $notOutputType = Utils::printSafe($type);
            $this->assertMatchesValidationMessage(
                $schema->validate(),
                [
                    [
                        'message' => "The type of BadObject.badField must be Output Type but got: {$notOutputType}.",
                    ],
                ]
            );
        }
    }

    /** @see it('rejects with relevant locations for a non-output type as an Object field type') */
    public function testRejectsWithRelevantLocationsForANonOutputTypeAsAnObjectFieldType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field: [SomeInputObject]
      }
      
      input SomeInputObject {
        field: String
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'The type of Query.field must be Output Type but got: [SomeInputObject].',
                    'locations' => [['line' => 3, 'column' => 16]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object implementing a non-Interface type') */
    public function testRejectsAnObjectImplementingANonInterfaceType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field: BadObject
      }
      
      input SomeInputObject {
        field: String
      }
      
      type BadObject implements SomeInputObject {
        field: String
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Type BadObject must only implement Interface types, it cannot implement SomeInputObject.',
                    'locations' => [['line' => 10, 'column' => 33]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object implementing the same interface twice') */
    public function testRejectsAnObjectImplementingTheSameInterfaceTwice(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field: AnotherObject
      }
      
      interface AnotherInterface {
        field: String
      }
      
      type AnotherObject implements AnotherInterface & AnotherInterface {
        field: String
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Type AnotherObject can only implement AnotherInterface once.',
                    'locations' => [['line' => 10, 'column' => 37], ['line' => 10, 'column' => 56]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object implementing the same interface twice due to extension') */
    public function testRejectsAnObjectImplementingTheSameInterfaceTwiceDueToExtension(): void
    {
        self::markTestIncomplete('extend does not work this way (yet).');
        $schema = BuildSchema::build('
      type Query {
        field: AnotherObject
      }
      
      interface AnotherInterface {
        field: String
      }
      
      type AnotherObject implements AnotherInterface {
        field: String
      }
      
      extend type AnotherObject implements AnotherInterface
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Type AnotherObject can only implement AnotherInterface once.',
                    'locations' => [['line' => 10, 'column' => 37], ['line' => 14, 'column' => 38]],
                ],
            ]
        );
    }

    // DESCRIBE: Type System: Interface extensions should be valid

    /** @see it('rejects an Object implementing the extended interface due to missing field') */
    public function testRejectsAnObjectImplementingTheExtendedInterfaceDueToMissingField(): void
    {
        $schema = BuildSchema::build('
          type Query {
            test: AnotherObject
          }
    
          interface AnotherInterface {
            field: String
          }
    
          type AnotherObject implements AnotherInterface {
            field: String
          }');

        $extendedSchema = SchemaExtender::extend(
            $schema,
            Parser::parse('
                extend interface AnotherInterface {
                  newField: String
                }
        
                extend type AnotherObject {
                  differentNewField: String
                }
            ')
        );

        $this->assertMatchesValidationMessage(
            $extendedSchema->validate(),
            [
                [
                    'message' => 'Interface field AnotherInterface.newField expected but AnotherObject does not provide it.',
                    'locations' => [
                        ['line' => 3, 'column' => 19],
                        ['line' => 7, 'column' => 7],
                        ['line' => 6, 'column' => 17],
                    ],
                ],
            ]
        );
    }

    /** @see it('rejects an Object implementing the extended interface due to missing field args') */
    public function testRejectsAnObjectImplementingTheExtendedInterfaceDueToMissingFieldArgs(): void
    {
        $schema = BuildSchema::build('
          type Query {
            test: AnotherObject
          }
    
          interface AnotherInterface {
            field: String
          }
    
          type AnotherObject implements AnotherInterface {
            field: String
          }');

        $extendedSchema = SchemaExtender::extend(
            $schema,
            Parser::parse('
                extend interface AnotherInterface {
                  newField(test: Boolean): String
                }
        
                extend type AnotherObject {
                  newField: String
                }
            ')
        );

        $this->assertMatchesValidationMessage(
            $extendedSchema->validate(),
            [
                [
                    'message' => 'Interface field argument AnotherInterface.newField(test:) expected but AnotherObject.newField does not provide it.',
                    'locations' => [
                        ['line' => 3, 'column' => 28],
                        ['line' => 7, 'column' => 19],
                    ],
                ],
            ]
        );
    }

    /** @see it('rejects Objects implementing the extended interface due to mismatching interface type') */
    public function testRejectsObjectsImplementingTheExtendedInterfaceDueToMismatchingInterfaceType(): void
    {
        $schema = BuildSchema::build('
          type Query {
            test: AnotherObject
          }
    
          interface AnotherInterface {
            field: String
          }
    
          type AnotherObject implements AnotherInterface {
            field: String
          }');

        $extendedSchema = SchemaExtender::extend(
            $schema,
            Parser::parse('
                extend interface AnotherInterface {
                  newInterfaceField: NewInterface
                }
        
                interface NewInterface {
                  newField: String
                }
        
                interface MismatchingInterface {
                  newField: String
                }
        
                extend type AnotherObject {
                  newInterfaceField: MismatchingInterface
                }
        
                # Required to prevent unused interface errors
                type DummyObject implements NewInterface & MismatchingInterface {
                  newField: String
                }
            ')
        );

        $this->assertMatchesValidationMessage(
            $extendedSchema->validate(),
            [
                [
                    'message' => 'Interface field AnotherInterface.newInterfaceField expects type NewInterface but AnotherObject.newInterfaceField is type MismatchingInterface.',
                    'locations' => [['line' => 3, 'column' => 38], ['line' => 15, 'column' => 38]],
                ],
            ]
        );
    }

    // DESCRIBE: Type System: Field arguments must have input types

    /** @see it('accepts an output type as an Interface field type') */
    public function testAcceptsAnOutputTypeAsAnInterfaceFieldType(): void
    {
        foreach ($this->outputTypes as $type) {
            $schema = $this->schemaWithInterfaceFieldOfType($type);
            self::assertSame([], $schema->validate());
        }
    }

    /** @throws InvariantViolation */
    private function schemaWithInterfaceFieldOfType(Type $fieldType): Schema
    {
        $BadInterfaceType = new InterfaceType([
            'name' => 'BadInterface',
            'fields' => [
                'badField' => ['type' => $fieldType],
            ],
        ]);

        $BadImplementingType = new ObjectType([
            'name' => 'BadImplementing',
            'interfaces' => [$BadInterfaceType],
            'fields' => [
                'badField' => ['type' => $fieldType],
            ],
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadInterfaceType],
                ],
            ]),
            'types' => [$BadImplementingType],
        ]);
    }

    /** @see it(`rejects a non-output type as an Interface field type: ${typeStr}`, () => { */
    public function testRejectsANonOutputTypeAsAnInterfaceFieldType(): void
    {
        foreach ($this->notOutputTypes as $type) {
            $schema = $this->schemaWithInterfaceFieldOfType($type);
            $notOutputType = Utils::printSafe($type);
            $this->assertMatchesValidationMessage(
                $schema->validate(),
                [
                    ['message' => "The type of BadImplementing.badField must be Output Type but got: {$notOutputType}."],
                    ['message' => "The type of BadInterface.badField must be Output Type but got: {$notOutputType}."],
                ]
            );
        }
    }

    // DESCRIBE: Type System: Input Object fields must have input types

    /** @see it('rejects a non-output type as an Interface field type with locations') */
    public function testRejectsANonOutputTypeAsAnInterfaceFieldTypeWithLocations(): void
    {
        $schema = BuildSchema::build('
      type Query {
        field: SomeInterface
      }
      
      interface SomeInterface {
        field: SomeInputObject
      }
      
      input SomeInputObject {
        foo: String
      }

      type SomeObject implements SomeInterface {
        field: SomeInputObject
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'The type of SomeInterface.field must be Output Type but got: SomeInputObject.',
                    'locations' => [['line' => 7, 'column' => 16]],
                ],
                [
                    'message' => 'The type of SomeObject.field must be Output Type but got: SomeInputObject.',
                    'locations' => [['line' => 15, 'column' => 16]],
                ],
            ]
        );
    }

    /** @see it('accepts an interface not implemented by at least one object') */
    public function testRejectsAnInterfaceNotImplementedByAtLeastOneObject(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: SomeInterface
      }

      interface SomeInterface {
        foo: String
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            []
        );
    }

    /** @see it('accepts an input type as a field arg type') */
    public function testAcceptsAnInputTypeAsAFieldArgType(): void
    {
        foreach ($this->inputTypes as $type) {
            $schema = $this->schemaWithArgOfType($type);
            self::assertSame([], $schema->validate());
        }
    }

    /** @throws InvariantViolation */
    private function schemaWithArgOfType(Type $argType): Schema
    {
        $BadObjectType = new ObjectType([
            'name' => 'BadObject',
            'fields' => [
                'badField' => [
                    'type' => Type::string(),
                    'args' => [
                        'badArg' => ['type' => $argType],
                    ],
                ],
            ],
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadObjectType],
                ],
            ]),
            'types' => [$this->SomeObjectType],
        ]);
    }

    // DESCRIBE: Objects must adhere to Interface they implement

    /** @see it('rejects a non-input type as a field arg type') */
    public function testRejectsANonInputTypeAsAFieldArgType(): void
    {
        foreach ($this->notInputTypes as $type) {
            $schema = $this->schemaWithArgOfType($type);
            $notInputType = Utils::printSafe($type);
            $this->assertMatchesValidationMessage(
                $schema->validate(),
                [
                    ['message' => "The type of BadObject.badField(badArg:) must be Input Type but got: {$notInputType}."],
                ]
            );
        }
    }

    /** @see it('rejects an required argument that is deprecated' */
    public function testRejectsARequiredArgumentThatIsDeprecated(): void
    {
        $schema = BuildSchema::build('
      directive @BadDirective(
        badArg: String! @deprecated
        optionalArg: String @deprecated
        anotherOptionalArg: String! = "" @deprecated
      ) on FIELD
      type Query {
        test(
          badArg: String! @deprecated
          optionalArg: String @deprecated
          anotherOptionalArg: String! = "" @deprecated
        ): String
      }
        ');

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Required argument String.test(badArg:) cannot be deprecated.');
        $schema->assertValid();
    }

    /** @see it('rejects a non-input type as a field arg with locations') */
    public function testANonInputTypeAsAFieldArgWithLocations(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test(arg: SomeObject): String
      }
      
      type SomeObject {
        foo: String
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'The type of Query.test(arg:) must be Input Type but got: SomeObject.',
                    'locations' => [['line' => 3, 'column' => 19]],
                ],
            ]
        );
    }

    /** @see it('accepts an input type as an input field type') */
    public function testAcceptsAnInputTypeAsAnInputFieldType(): void
    {
        foreach ($this->inputTypes as $type) {
            $schema = $this->schemaWithInputFieldOfType($type);
            self::assertSame([], $schema->validate());
        }
    }

    /** @throws InvariantViolation */
    private function schemaWithInputFieldOfType(Type $inputFieldType): Schema
    {
        // @phpstan-ignore-next-line intentionally wrong
        $badInputObjectType = new InputObjectType([
            'name' => 'BadInputObject',
            'fields' => [
                'badField' => [
                    'type' => $inputFieldType,
                ],
            ],
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => [
                        'type' => Type::string(),
                        'args' => [
                            'badArg' => ['type' => $badInputObjectType],
                        ],
                    ],
                ],
            ]),
            'types' => [$this->SomeObjectType],
        ]);
    }

    /** @see it('rejects a non-input type as an input field type') */
    public function testRejectsANonInputTypeAsAnInputFieldType(): void
    {
        foreach ($this->notInputTypes as $type) {
            $schema = $this->schemaWithInputFieldOfType($type);
            $notInputType = Utils::printSafe($type);
            $this->assertMatchesValidationMessage(
                $schema->validate(),
                [
                    [
                        'message' => "The type of BadInputObject.badField must be Input Type but got: {$notInputType}.",
                    ],
                ]
            );
        }
    }

    /** @see it('rejects a non-input type as an input object field with locations') */
    public function testRejectsANonInputTypeAsAnInputObjectFieldWithLocations(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test(arg: SomeInputObject): String
      }
      
      input SomeInputObject {
        foo: SomeObject
      }
      
      type SomeObject {
        bar: String
      }
        ');
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'The type of SomeInputObject.foo must be Input Type but got: SomeObject.',
                    'locations' => [['line' => 7, 'column' => 14]],
                ],
            ]
        );
    }

    /** @see it('accepts an Object which implements an Interface') */
    public function testAcceptsAnObjectWhichImplementsAnInterface(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }
      
      interface AnotherInterface {
        field(input: String): String
      }
      
      type AnotherObject implements AnotherInterface {
        field(input: String): String
      }
        ');

        self::assertSame(
            [],
            $schema->validate()
        );
    }

    /** @see it('accepts an Object which implements an Interface along with more fields') */
    public function testAcceptsAnObjectWhichImplementsAnInterfaceAlongWithMoreFields(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field(input: String): String
      }

      type AnotherObject implements AnotherInterface {
        field(input: String): String
        anotherField: String
      }
        ');

        self::assertSame(
            [],
            $schema->validate()
        );
    }

    /** @see it('accepts an Object which implements an Interface field along with additional optional arguments') */
    public function testAcceptsAnObjectWhichImplementsAnInterfaceFieldAlongWithAdditionalOptionalArguments(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field(input: String): String
      }

      type AnotherObject implements AnotherInterface {
        field(input: String, anotherInput: String): String
      }
        ');

        self::assertSame(
            [],
            $schema->validate()
        );
    }

    /** @see it('rejects an Object missing an Interface field') */
    public function testRejectsAnObjectMissingAnInterfaceField(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field(input: String): String
      }

      type AnotherObject implements AnotherInterface {
        anotherField: String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field AnotherInterface.field expected but AnotherObject does not provide it.',
                    'locations' => [['line' => 7, 'column' => 9], ['line' => 10, 'column' => 7]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object with an incorrectly typed Interface field') */
    public function testRejectsAnObjectWithAnIncorrectlyTypedInterfaceField(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field(input: String): String
      }

      type AnotherObject implements AnotherInterface {
        field(input: String): Int
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field AnotherInterface.field expects type String but AnotherObject.field is type Int.',
                    'locations' => [['line' => 7, 'column' => 31], ['line' => 11, 'column' => 31]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object with a differently typed Interface field') */
    public function testRejectsAnObjectWithADifferentlyTypedInterfaceField(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      type A { foo: String }
      type B { foo: String }

      interface AnotherInterface {
        field: A
      }

      type AnotherObject implements AnotherInterface {
        field: B
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field AnotherInterface.field expects type A but AnotherObject.field is type B.',
                    'locations' => [['line' => 10, 'column' => 16], ['line' => 14, 'column' => 16]],
                ],
            ]
        );
    }

    /** @see it('accepts an Object with a subtyped Interface field (interface)') */
    public function testAcceptsAnObjectWithASubtypedInterfaceFieldForInterface(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field: AnotherInterface
      }

      type AnotherObject implements AnotherInterface {
        field: AnotherObject
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('accepts an Object with a subtyped Interface field (union)') */
    public function testAcceptsAnObjectWithASubtypedInterfaceFieldForUnion(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      type SomeObject {
        field: String
      }

      union SomeUnionType = SomeObject

      interface AnotherInterface {
        field: SomeUnionType
      }

      type AnotherObject implements AnotherInterface {
        field: SomeObject
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects an Object missing an Interface argument') */
    public function testRejectsAnObjectMissingAnInterfaceArgument(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field(input: String): String
      }

      type AnotherObject implements AnotherInterface {
        field: String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field argument AnotherInterface.field(input:) expected '
                    . 'but AnotherObject.field does not provide it.',
                    'locations' => [['line' => 7, 'column' => 15], ['line' => 11, 'column' => 9]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object with an incorrectly typed Interface argument') */
    public function testRejectsAnObjectWithAnIncorrectlyTypedInterfaceArgument(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field(input: String): String
      }

      type AnotherObject implements AnotherInterface {
        field(input: Int): String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field argument AnotherInterface.field(input:) expects '
                    . 'type String but AnotherObject.field(input:) is type Int.',
                    'locations' => [['line' => 7, 'column' => 22], ['line' => 11, 'column' => 22]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object with both an incorrectly typed field and argument') */
    public function testRejectsAnObjectWithBothAnIncorrectlyTypedFieldAndArgument(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field(input: String): String
      }

      type AnotherObject implements AnotherInterface {
        field(input: Int): Int
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field AnotherInterface.field expects type String but AnotherObject.field is type Int.',
                    'locations' => [['line' => 7, 'column' => 31], ['line' => 11, 'column' => 28]],
                ],
                [
                    'message' => 'Interface field argument AnotherInterface.field(input:) expects '
                        . 'type String but AnotherObject.field(input:) is type Int.',
                    'locations' => [['line' => 7, 'column' => 22], ['line' => 11, 'column' => 22]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object which implements an Interface field along with additional required arguments') */
    public function testRejectsAnObjectWhichImplementsAnInterfaceFieldAlongWithAdditionalRequiredArguments(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field(baseArg: String): String
      }

      type AnotherObject implements AnotherInterface {
        field(
          baseArg: String,
          requiredArg: String!
          optionalArg1: String,
          optionalArg2: String = "",
        ): String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Object field AnotherObject.field includes required argument '
                    . 'requiredArg that is missing from the Interface field '
                    . 'AnotherInterface.field.',
                    'locations' => [['line' => 13, 'column' => 11], ['line' => 7, 'column' => 9]],
                ],
            ]
        );
    }

    /** @see it('accepts an Object with an equivalently wrapped Interface field type') */
    public function testAcceptsAnObjectWithAnEquivalentlyWrappedInterfaceFieldType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field: [String]!
      }

      type AnotherObject implements AnotherInterface {
        field: [String]!
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects an Object with a non-list Interface field list type') */
    public function testRejectsAnObjectWithANonListInterfaceFieldListType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field: [String]
      }

      type AnotherObject implements AnotherInterface {
        field: String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field AnotherInterface.field expects type [String] '
                    . 'but AnotherObject.field is type String.',
                    'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object with a list Interface field non-list type') */
    public function testRejectsAnObjectWithAListInterfaceFieldNonListType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field: String
      }

      type AnotherObject implements AnotherInterface {
        field: [String]
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field AnotherInterface.field expects type String but '
                    . 'AnotherObject.field is type [String].',
                    'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
                ],
            ]
        );
    }

    /** @see it('accepts an Object with a subset non-null Interface field type') */
    public function testAcceptsAnObjectWithASubsetNonNullInterfaceFieldType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field: String
      }

      type AnotherObject implements AnotherInterface {
        field: String!
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects an Object with a superset nullable Interface field type') */
    public function testRejectsAnObjectWithASupersetNullableInterfaceFieldType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field: String!
      }

      type AnotherObject implements AnotherInterface {
        field: String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field AnotherInterface.field expects type String! but AnotherObject.field is type String.',
                    'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object missing a transitive interface') */
    public function testRejectsAnObjectMissingATransitiveInterface(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }
      
      interface SuperInterface {
        field: String!
      }
      
      interface AnotherInterface implements SuperInterface {
        field: String!
      }
      
      type AnotherObject implements AnotherInterface {
        field: String!
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Type AnotherObject must implement SuperInterface because it is implemented by AnotherInterface.',
                    'locations' => [['line' => 10, 'column' => 45], ['line' => 14, 'column' => 37]],
                ],
            ]
        );
    }

    /** @see it('accepts an Interface which implements an Interface') */
    public function testAcceptsAnInterfaceWhichImplementsAnInterface(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }

      interface ParentInterface {
        field(input: String): String
      }
      
      interface ChildInterface implements ParentInterface {
        field(input: String): String
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('accepts an Interface which implements an Interface along with more fields') */
    public function testAcceptsAnInterfaceWhichImplementsAnInterfaceAlongWithMoreFields(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }

      interface ParentInterface {
        field(input: String): String
      }
      
      interface ChildInterface implements ParentInterface {
        field(input: String): String
        anotherField: String
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('accepts an Interface which implements an Interface field along with additional optional arguments') */
    public function testAcceptsAnInterfaceWhichImplementsAnInterfaceFieldAlongWithAdditionalOptionalArguments(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }

      interface ParentInterface {
        field(input: String): String
      }
      
      interface ChildInterface implements ParentInterface {
        field(input: String, anotherInput: String): String
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects an Interface missing an Interface field') */
    public function testRejectsAnInterfaceMissingAnInterfaceField(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }

      interface ParentInterface {
        field(input: String): String
      }
      
      interface ChildInterface implements ParentInterface {
        anotherField: String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field ParentInterface.field expected but ChildInterface does not provide it.',
                    'locations' => [['line' => 7, 'column' => 9], ['line' => 10, 'column' => 7]],
                ],
            ]
        );
    }

    /** @see it('rejects an Interface with an incorrectly typed Interface field') */
    public function testRejectsAnInterfaceWithAnIncorrectlyTypedInterfaceField(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }

      interface ParentInterface {
        field(input: String): String
      }
      
      interface ChildInterface implements ParentInterface {
        field(input: String): Int
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field ParentInterface.field expects type String but ChildInterface.field is type Int.',
                    'locations' => [['line' => 7, 'column' => 31], ['line' => 11, 'column' => 31]],
                ],
            ]
        );
    }

    /** @see it('rejects an Interface with a differently typed Interface field') */
    public function testRejectsAnInterfaceWithADifferentlyTypedInterfaceField(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }
      
      type A { foo: String }
      type B { foo: String }

      interface ParentInterface {
        field: A
      }
      
      interface ChildInterface implements ParentInterface {
        field: B
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field ParentInterface.field expects type A but ChildInterface.field is type B.',
                    'locations' => [['line' => 10, 'column' => 16], ['line' => 14, 'column' => 16]],
                ],
            ]
        );
    }

    /** @see it('accepts an interface with a subtyped Interface field (interface)') */
    public function testAcceptsAnInterfaceWithASubtypedInterfaceFieldInterface(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }
      
      interface ParentInterface {
        field: ParentInterface
      }
      
      interface ChildInterface implements ParentInterface {
        field: ChildInterface
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('accepts an interface with a subtyped Interface field (union)') */
    public function testAcceptsAnInterfaceWithASubtypedInterfaceFieldUnion(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }
      
      type SomeObject {
        field: String
      }
      union SomeUnionType = SomeObject
      
      interface ParentInterface {
        field: SomeUnionType
      }
      
      interface ChildInterface implements ParentInterface {
        field: SomeObject
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects an Interface with an Interface argument') */
    public function testRejectsAnInterfaceMissingAnInterfaceArgument(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }

      interface ParentInterface {
        field(input: String): String
      }
      
      interface ChildInterface implements ParentInterface {
        field: String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field argument ParentInterface.field(input:) expected '
                    . 'but ChildInterface.field does not provide it.',
                    'locations' => [['line' => 7, 'column' => 15], ['line' => 11, 'column' => 9]],
                ],
            ]
        );
    }

    /** @see it('rejects an Interface with an incorrectly typed Interface argument') */
    public function testRejectsAnInterfaceWithAnIncorrectlyTypedInterfaceArgument(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }

      interface ParentInterface {
        field(input: String): String
      }
      
      interface ChildInterface implements ParentInterface {
        field(input: Int): String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field argument ParentInterface.field(input:) expects type String '
                    . 'but ChildInterface.field(input:) is type Int.',
                    'locations' => [['line' => 7, 'column' => 22], ['line' => 11, 'column' => 22]],
                ],
            ]
        );
    }

    /** @see it('rejects an Interface with both an incorrectly typed field and argument') */
    public function testRejectsAnInterfaceWithBothAnIncorrectlyTypedFieldAndArgument(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }

      interface ParentInterface {
        field(input: String): String
      }
      
      interface ChildInterface implements ParentInterface {
        field(input: Int): Int
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field ParentInterface.field expects type String but ChildInterface.field is type Int.',
                    'locations' => [['line' => 7, 'column' => 31], ['line' => 11, 'column' => 28]],
                ],
                [
                    'message' => 'Interface field argument ParentInterface.field(input:) expects type String '
                        . 'but ChildInterface.field(input:) is type Int.',
                    'locations' => [['line' => 7, 'column' => 22], ['line' => 11, 'column' => 22]],
                ],
            ]
        );
    }

    /** @see it('rejects an Interface which implements an Interface field along with additional required arguments') */
    public function testRejectsAnInterfaceWhichImplementsAnInterfaceFieldAlongWithAdditionalRequiredArguments(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }

      interface ParentInterface {
        field(baseArg: String): String
      }
      
      interface ChildInterface implements ParentInterface {
        field(
          baseArg: String,
          requiredArg: String!
          optionalArg1: String,
          optionalArg2: String = "",
        ): String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Object field ChildInterface.field includes required argument requiredArg '
                    . 'that is missing from the Interface field ParentInterface.field.',
                    'locations' => [['line' => 13, 'column' => 11], ['line' => 7, 'column' => 9]],
                ],
            ]
        );
    }

    /** @see it('accepts an Interface with an equivalently wrapped Interface field type') */
    public function testAcceptsAnInterfaceWithAnEquivalentlyWrappedInterfaceFieldType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }
      
      interface ParentInterface {
        field: [String]!
      }
      
      interface ChildInterface implements ParentInterface {
        field: [String]!
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects an Interface with a non-list Interface field list type') */
    public function testRejectsAnInterfaceWithANonListInterfaceFieldListType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }
      
      interface ParentInterface {
        field: [String]
      }
      
      interface ChildInterface implements ParentInterface {
        field: String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field ParentInterface.field expects type [String] '
                    . 'but ChildInterface.field is type String.',
                    'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
                ],
            ]
        );
    }

    /** @see it('rejects an Interface with a list Interface field non-list type') */
    public function testRejectsAnInterfaceWithAListInterfaceFieldNonListType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }
      
      interface ParentInterface {
        field: String
      }
      
      interface ChildInterface implements ParentInterface {
        field: [String]
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field ParentInterface.field expects type String '
                    . 'but ChildInterface.field is type [String].',
                    'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
                ],
            ]
        );
    }

    /** @see it('accepts an Interface with a subset non-null Interface field type') */
    public function testAcceptsAnInterfaceWithASubsetNonNullInterfaceFieldType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }
      
      interface ParentInterface {
        field: String
      }
      
      interface ChildInterface implements ParentInterface {
        field: String!
      }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects an Interface with a superset nullable interface field type') */
    public function testRejectsAnInterfaceWithASupsersetNullableInterfaceFieldType(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }
      
      interface ParentInterface {
        field: String!
      }
      
      interface ChildInterface implements ParentInterface {
        field: String
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Interface field ParentInterface.field expects type String! but ChildInterface.field is type String.',
                    'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
                ],
            ]
        );
    }

    /** @see it('rejects an Object missing a transitive interface') */
    public function testRejectsAnInterfaceMissingATransitiveInterface(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: ChildInterface
      }
      
      interface SuperInterface {
        field: String!
      }
      
      interface ParentInterface implements SuperInterface {
        field: String!
      }
      
      interface ChildInterface implements ParentInterface {
        field: String!
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Type ChildInterface must implement SuperInterface because it is implemented by ParentInterface.',
                    'locations' => [['line' => 10, 'column' => 44], ['line' => 14, 'column' => 43]],
                ],
            ]
        );
    }

    /** @see it('rejects a self reference interface') */
    public function testRejectsASelfReferenceInterface(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: FooInterface
      }
      
      interface FooInterface implements FooInterface {
        field: String!
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Type FooInterface cannot implement itself because it would create a circular reference.',
                    'locations' => [['line' => 6, 'column' => 41]],
                ],
            ]
        );
    }

    /** @see it('rejects a circulare Interface implementation') */
    public function testRejectsACircularInterfaceImplementation(): void
    {
        $schema = BuildSchema::build('
      type Query {
        test: FooInterface
      }
      
      interface FooInterface implements BarInterface {
        field: String!
      }
      
      interface BarInterface implements FooInterface {
        field: String!
      }
        ');

        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Type FooInterface cannot implement BarInterface because it would create a circular reference.',
                    'locations' => [['line' => 10, 'column' => 41], ['line' => 6, 'column' => 41]],
                ],
                [
                    'message' => 'Type BarInterface cannot implement FooInterface because it would create a circular reference.',
                    'locations' => [['line' => 6, 'column' => 41], ['line' => 10, 'column' => 41]],
                ],
            ]
        );
    }

    public function testRejectsDifferentInstancesOfTheSameType(): void
    {
        // Invalid: always creates new instance vs returning one from registry
        $typeLoader = static function ($name): ?ObjectType {
            switch ($name) {
                case 'Query':
                    return new ObjectType([
                        'name' => 'Query',
                        'fields' => [
                            'test' => Type::string(),
                        ],
                    ]);

                default:
                    return null;
            }
        };

        $query = $typeLoader('Query');
        assert($query instanceof ObjectType);

        $schema = new Schema([
            'query' => $query,
            'typeLoader' => $typeLoader,
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Type loader returns different instance for Query than field/argument definitions. '
            . 'Make sure you always return the same instance for the same type name.'
        );
        $schema->assertValid();
    }

    // DESCRIBE: Type System: Schema directives must validate

    /** @see it('accepts a Schema with valid directives') */
    public function testAcceptsASchemaWithValidDirectives(): void
    {
        $schema = BuildSchema::build('
          schema @testA @testB {
            query: Query
          }
    
          type Query @testA @testB {
            test: AnInterface @testC
          }
    
          directive @testA on SCHEMA | OBJECT | INTERFACE | UNION | SCALAR | INPUT_OBJECT | ENUM
          directive @testB on SCHEMA | OBJECT | INTERFACE | UNION | SCALAR | INPUT_OBJECT | ENUM
          directive @testC on FIELD_DEFINITION | ARGUMENT_DEFINITION | ENUM_VALUE | INPUT_FIELD_DEFINITION
          directive @testD on FIELD_DEFINITION | ARGUMENT_DEFINITION | ENUM_VALUE | INPUT_FIELD_DEFINITION
    
          interface AnInterface @testA {
            field: String! @testC
          }
    
          type TypeA implements AnInterface @testA {
            field(arg: SomeInput @testC): String! @testC @testD
          }
    
          type TypeB @testB @testA {
            scalar_field: SomeScalar @testC
            enum_field: SomeEnum @testC @testD
          }
    
          union SomeUnion @testA = TypeA | TypeB
    
          scalar SomeScalar @testA @testB
    
          enum SomeEnum @testA @testB {
            SOME_VALUE @testC
          }
    
          input SomeInput @testA @testB {
            some_input_field: String @testC
          }
        ');

        self::assertSame([], $schema->validate());
    }

    /** @see it('rejects a Schema with directive defined multiple times') */
    public function testRejectsASchemaWithDirectiveDefinedMultipleTimes(): void
    {
        $schema = BuildSchema::build('
          type Query {
            test: String
          }
    
          directive @testA on SCHEMA
          directive @testA on SCHEMA
        ', null, ['assumeValidSDL' => true]);
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Directive @testA defined multiple times.',
                    'locations' => [['line' => 6, 'column' => 11], ['line' => 7, 'column' => 11]],
                ],
            ]
        );
    }

    public function testRejectsASchemaWithDirectivesWithWrongArgs(): void
    {
        // Not using SDL as the duplicate arg name error is prevented by the parser

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                [
                    'name' => 'test',
                    'type' => Type::int(),
                ],
            ],
        ]);
        // @phpstan-ignore-next-line intentionally wrong
        $directive = new Directive([
            'name' => 'test',
            'args' => [
                [
                    'name' => '__arg',
                    'type' => Type::int(),
                ],
                [
                    'name' => 'dup',
                    'type' => Type::int(),
                ],
                [
                    'name' => 'dup',
                    'type' => Type::int(),
                ],
                [
                    'name' => 'query',
                    'type' => $query,
                ],
            ],
            'locations' => [DirectiveLocation::QUERY],
        ]);
        $schema = new Schema([
            'query' => $query,
            'directives' => [$directive],
        ]);
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                ['message' => 'Name "__arg" must not begin with "__", which is reserved by GraphQL introspection.'],
                ['message' => 'Argument @test(dup:) can only be defined once.'],
                ['message' => 'The type of @test(query:) must be Input Type but got: Query.'],
            ]
        );
    }

    public function testAllowsRepeatableDirectivesMultipleTimesAtTheSameLocation(): void
    {
        $schema = BuildSchema::build('
          directive @repeatable repeatable on OBJECT

          type Query @repeatable @repeatable {
            foo: ID
          }
        ', null, ['assumeValid' => true]);
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            []
        );
    }

    /** @see it('rejects a Schema with directive used again in extension') */
    public function testRejectsASchemaWithSameDefinitionDirectiveUsedTwice(): void
    {
        $schema = BuildSchema::build('
          directive @testA on OBJECT
    
          type Query @testA {
            test: String
          }
        ');

        $extensions = Parser::parse('
          extend type Query @testA
        ');

        $extendedSchema = SchemaExtender::extend($schema, $extensions);

        $this->assertMatchesValidationMessage(
            $extendedSchema->validate(),
            [
                [
                    'message' => 'Non-repeatable directive @testA used more than once at the same location.',
                    'locations' => [['line' => 4, 'column' => 22], ['line' => 2, 'column' => 29]],
                ],
            ]
        );
    }
}
