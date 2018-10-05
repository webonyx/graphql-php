<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;
use function array_map;
use function array_merge;
use function count;
use function implode;
use function sprintf;

class ValidationTest extends TestCase
{
    /** @var ScalarType */
    public $SomeScalarType;

    /** @var ObjectType */
    public $SomeObjectType;

    /** @var UnionType */
    public $SomeUnionType;

    /** @var InterfaceType */
    public $SomeInterfaceType;

    /** @var EnumType */
    public $SomeEnumType;

    /** @var InputObjectType */
    public $SomeInputObjectType;

    /** @var mixed[] */
    public $outputTypes;

    /** @var mixed[] */
    public $notOutputTypes;

    /** @var mixed[] */
    public $inputTypes;

    /** @var mixed[] */
    public $notInputTypes;

    /** @var float */
    public $Number;

    public function setUp()
    {
        $this->Number = 1;

        $this->SomeScalarType = new CustomScalarType([
            'name'         => 'SomeScalar',
            'serialize'    => static function () {
            },
            'parseValue'   => static function () {
            },
            'parseLiteral' => static function () {
            },
        ]);

        $this->SomeObjectType = new ObjectType([
            'name'       => 'SomeObject',
            'fields'     => ['f' => ['type' => Type::string()]],
            'interfaces' => function () {
                return [$this->SomeInterfaceType];
            },
        ]);

        $this->SomeUnionType = new UnionType([
            'name'  => 'SomeUnion',
            'types' => [$this->SomeObjectType],
        ]);

        $this->SomeInterfaceType = new InterfaceType([
            'name'   => 'SomeInterface',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $this->SomeEnumType = new EnumType([
            'name'   => 'SomeEnum',
            'values' => [
                'ONLY' => [],
            ],
        ]);

        $this->SomeInputObjectType = new InputObjectType([
            'name'   => 'SomeInputObject',
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

        $this->notOutputTypes   = $this->withModifiers([
            $this->SomeInputObjectType,
        ]);
        $this->notOutputTypes[] = $this->Number;

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

        $this->notInputTypes[] = $this->Number;

        Warning::suppress(Warning::WARNING_NOT_A_TYPE);
    }

    private function withModifiers($types)
    {
        return array_merge(
            $types,
            Utils::map(
                $types,
                static function ($type) {
                    return Type::listOf($type);
                }
            ),
            Utils::map(
                $types,
                static function ($type) {
                    return Type::nonNull($type);
                }
            ),
            Utils::map(
                $types,
                static function ($type) {
                    return Type::nonNull(Type::listOf($type));
                }
            )
        );
    }

    public function tearDown()
    {
        parent::tearDown();
        Warning::enable(Warning::WARNING_NOT_A_TYPE);
    }

    public function testRejectsTypesWithoutNames() : void
    {
        $this->assertEachCallableThrows(
            [
                static function () {
                    return new ObjectType([]);
                },
                static function () {
                    return new EnumType([]);
                },
                static function () {
                    return new InputObjectType([]);
                },
                static function () {
                    return new UnionType([]);
                },
                static function () {
                    return new InterfaceType([]);
                },
            ],
            'Must provide name.'
        );
    }

    /**
     * DESCRIBE: Type System: A Schema must have Object root types
     */
    private function assertEachCallableThrows($closures, $expectedError)
    {
        foreach ($closures as $index => $factory) {
            try {
                $factory();
                $this->fail('Expected exception not thrown for entry ' . $index);
            } catch (InvariantViolation $e) {
                self::assertEquals($expectedError, $e->getMessage(), 'Error in callable #' . $index);
            }
        }
    }

    /**
     * @see it('accepts a Schema whose query type is an object type')
     */
    public function testAcceptsASchemaWhoseQueryTypeIsAnObjectType() : void
    {
        $schema = BuildSchema::build('
      type Query {
        test: String
      }
        ');
        self::assertEquals([], $schema->validate());

        $schemaWithDef = BuildSchema::build('
      schema {
        query: QueryRoot
      }
      type QueryRoot {
        test: String
      }
    ');
        self::assertEquals([], $schemaWithDef->validate());
    }

    /**
     * @see it('accepts a Schema whose query and mutation types are object types')
     */
    public function testAcceptsASchemaWhoseQueryAndMutationTypesAreObjectTypes() : void
    {
        $schema = BuildSchema::build('
      type Query {
        test: String
      }

      type Mutation {
        test: String
      }
        ');
        self::assertEquals([], $schema->validate());

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
        self::assertEquals([], $schema->validate());
    }

    /**
     * @see it('accepts a Schema whose query and subscription types are object types')
     */
    public function testAcceptsASchemaWhoseQueryAndSubscriptionTypesAreObjectTypes() : void
    {
        $schema = BuildSchema::build('
      type Query {
        test: String
      }

      type Subscription {
        test: String
      }
        ');
        self::assertEquals([], $schema->validate());

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
        self::assertEquals([], $schema->validate());
    }

    /**
     * @see it('rejects a Schema without a query type')
     */
    public function testRejectsASchemaWithoutAQueryType() : void
    {
        $schema = BuildSchema::build('
      type Mutation {
        test: String
      }
        ');

        $this->assertContainsValidationMessage(
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

        $this->assertContainsValidationMessage(
            $schemaWithDef->validate(),
            [[
                'message'   => 'Query root type must be provided.',
                'locations' => [['line' => 2, 'column' => 7]],
            ],
            ]
        );
    }

    /**
     * @param InvariantViolation[]|Error[] $array
     * @param string[][]                   $messages
     */
    private function assertContainsValidationMessage($array, $messages)
    {
        self::assertCount(
            count($messages),
            $array,
            sprintf('For messages: %s', $messages[0]['message']) . "\n" .
            "Received: \n" .
            implode(
                "\n",
                array_map(
                    static function ($error) {
                        return $error->getMessage();
                    },
                    $array
                )
            )
        );
        foreach ($array as $index => $error) {
            if (! isset($messages[$index]) || ! $error instanceof Error) {
                $this->fail('Received unexpected error: ' . $error->getMessage());
            }
            self::assertEquals($messages[$index]['message'], $error->getMessage());
            $errorLocations = [];
            foreach ($error->getLocations() as $location) {
                $errorLocations[] = $location->toArray();
            }
            self::assertEquals(
                $messages[$index]['locations'] ?? [],
                $errorLocations
            );
        }
    }

    /**
     * @see it('rejects a Schema whose query root type is not an Object type')
     */
    public function testRejectsASchemaWhoseQueryTypeIsNotAnObjectType() : void
    {
        $schema = BuildSchema::build('
      input Query {
        test: String
      }
        ');

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Query root type must be Object type, it cannot be Query.',
                'locations' => [['line' => 2, 'column' => 7]],
            ],
            ]
        );

        $schemaWithDef = BuildSchema::build('
      schema {
        query: SomeInputObject
      }

      input SomeInputObject {
        test: String
      }
        ');

        $this->assertContainsValidationMessage(
            $schemaWithDef->validate(),
            [[
                'message'   => 'Query root type must be Object type, it cannot be SomeInputObject.',
                'locations' => [['line' => 3, 'column' => 16]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects a Schema whose mutation type is an input type')
     */
    public function testRejectsASchemaWhoseMutationTypeIsAnInputType() : void
    {
        $schema = BuildSchema::build('
      type Query {
        field: String
      }

      input Mutation {
        test: String
      }
        ');

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Mutation root type must be Object type if provided, it cannot be Mutation.',
                'locations' => [['line' => 6, 'column' => 7]],
            ],
            ]
        );

        $schemaWithDef = BuildSchema::build('
      schema {
        query: Query
        mutation: SomeInputObject
      }

      type Query {
        field: String
      }

      input SomeInputObject {
        test: String
      }
        ');

        $this->assertContainsValidationMessage(
            $schemaWithDef->validate(),
            [[
                'message'   => 'Mutation root type must be Object type if provided, it cannot be SomeInputObject.',
                'locations' => [['line' => 4, 'column' => 19]],
            ],
            ]
        );
    }

    // DESCRIBE: Type System: Objects must have fields

    /**
     * @see it('rejects a Schema whose subscription type is an input type')
     */
    public function testRejectsASchemaWhoseSubscriptionTypeIsAnInputType() : void
    {
        $schema = BuildSchema::build('
      type Query {
        field: String
      }

      input Subscription {
        test: String
      }
        ');

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Subscription root type must be Object type if provided, it cannot be Subscription.',
                'locations' => [['line' => 6, 'column' => 7]],
            ],
            ]
        );

        $schemaWithDef = BuildSchema::build('
      schema {
        query: Query
        subscription: SomeInputObject
      }

      type Query {
        field: String
      }

      input SomeInputObject {
        test: String
      }
        ');

        $this->assertContainsValidationMessage(
            $schemaWithDef->validate(),
            [[
                'message'   => 'Subscription root type must be Object type if provided, it cannot be SomeInputObject.',
                'locations' => [['line' => 4, 'column' => 23]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects a Schema whose directives are incorrectly typed')
     */
    public function testRejectsASchemaWhoseDirectivesAreIncorrectlyTyped() : void
    {
        $schema = new Schema([
            'query'      => $this->SomeObjectType,
            'directives' => ['somedirective'],
        ]);

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [['message' => 'Expected directive but got: somedirective.']]
        );
    }

    /**
     * @see it('accepts an Object type with fields object')
     */
    public function testAcceptsAnObjectTypeWithFieldsObject() : void
    {
        $schema = BuildSchema::build('
      type Query {
        field: SomeObject
      }

      type SomeObject {
        field: String
      }
        ');

        self::assertEquals([], $schema->validate());
    }

    /**
     * @see it('rejects an Object type with missing fields')
     */
    public function testRejectsAnObjectTypeWithMissingFields() : void
    {
        $schema = BuildSchema::build('
      type Query {
        test: IncompleteObject
      }

      type IncompleteObject
        ');

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Type IncompleteObject must define one or more fields.',
                'locations' => [['line' => 6, 'column' => 7]],
            ],
            ]
        );

        $manualSchema = $this->schemaWithFieldType(
            new ObjectType([
                'name'   => 'IncompleteObject',
                'fields' => [],
            ])
        );

        $this->assertContainsValidationMessage(
            $manualSchema->validate(),
            [['message' => 'Type IncompleteObject must define one or more fields.']]
        );

        $manualSchema2 = $this->schemaWithFieldType(
            new ObjectType([
                'name'   => 'IncompleteObject',
                'fields' => static function () {
                    return [];
                },
            ])
        );

        $this->assertContainsValidationMessage(
            $manualSchema2->validate(),
            [['message' => 'Type IncompleteObject must define one or more fields.']]
        );
    }

    /**
     * DESCRIBE: Type System: Fields args must be properly named
     */
    private function schemaWithFieldType($type) : Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => ['f' => ['type' => $type]],
            ]),
            'types' => [$type],
        ]);
    }

    /**
     * @see it('rejects an Object type with incorrectly named fields')
     */
    public function testRejectsAnObjectTypeWithIncorrectlyNamedFields() : void
    {
        $schema = $this->schemaWithFieldType(
            new ObjectType([
                'name'   => 'SomeObject',
                'fields' => [
                    'bad-name-with-dashes' => ['type' => Type::string()],
                ],
            ])
        );

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message' => 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but ' .
                    '"bad-name-with-dashes" does not.',
            ],
            ]
        );
    }

    /**
     * DESCRIBE: Type System: Union types must be valid
     */
    public function testAcceptsShorthandNotationForFields() : void
    {
        $this->expectNotToPerformAssertions();
        $schema = $this->schemaWithFieldType(
            new ObjectType([
                'name'   => 'SomeObject',
                'fields' => [
                    'field' => Type::string(),
                ],
            ])
        );
        $schema->assertValid();
    }

    /**
     * @see it('accepts field args with valid names')
     */
    public function testAcceptsFieldArgsWithValidNames() : void
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'goodField' => [
                    'type' => Type::string(),
                    'args' => [
                        'goodArg' => ['type' => Type::string()],
                    ],
                ],
            ],
        ]));
        self::assertEquals([], $schema->validate());
    }

    /**
     * @see it('rejects field arg with invalid names')
     */
    public function testRejectsFieldArgWithInvalidNames() : void
    {
        $QueryType = new ObjectType([
            'name'   => 'SomeObject',
            'fields' => [
                'badField' => [
                    'type' => Type::string(),
                    'args' => [
                        'bad-name-with-dashes' => ['type' => Type::string()],
                    ],
                ],
            ],
        ]);
        $schema    = new Schema(['query' => $QueryType]);

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [['message' => 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "bad-name-with-dashes" does not.']]
        );
    }

    /**
     * @see it('accepts a Union type with member types')
     */
    public function testAcceptsAUnionTypeWithArrayTypes() : void
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

        self::assertEquals([], $schema->validate());
    }

    // DESCRIBE: Type System: Input Objects must have fields

    /**
     * @see it('rejects a Union type with empty types')
     */
    public function testRejectsAUnionTypeWithEmptyTypes() : void
    {
        $schema = BuildSchema::build('
      type Query {
        test: BadUnion
      }

      union BadUnion
        ');
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Union type BadUnion must define one or more member types.',
                'locations' => [['line' => 6, 'column' => 7]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects a Union type with duplicated member type')
     */
    public function testRejectsAUnionTypeWithDuplicatedMemberType() : void
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
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Union type BadUnion can only include type TypeA once.',
                'locations' => [['line' => 15, 'column' => 11], ['line' => 17, 'column' => 11]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects a Union type with non-Object members types')
     */
    public function testRejectsAUnionTypeWithNonObjectMembersType() : void
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
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Union type BadUnion can only include Object types, ' .
                    'it cannot include String.',
                'locations' => [['line' => 16, 'column' => 11]],
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
                new UnionType(['name' => 'BadUnion', 'types' => [$memberType]])
            );
            $this->assertContainsValidationMessage(
                $badSchema->validate(),
                [[
                    'message' => 'Union type BadUnion can only include Object types, ' .
                        'it cannot include ' . Utils::printSafe($memberType) . '.',
                ],
                ]
            );
        }
    }

    // DESCRIBE: Type System: Enum types must be well defined

    /**
     * @see it('accepts an Input Object type with fields')
     */
    public function testAcceptsAnInputObjectTypeWithFields() : void
    {
        $schema = BuildSchema::build('
      type Query {
        field(arg: SomeInputObject): String
      }

      input SomeInputObject {
        field: String
      }
        ');
        self::assertEquals([], $schema->validate());
    }

    /**
     * @see it('rejects an Input Object type with missing fields')
     */
    public function testRejectsAnInputObjectTypeWithMissingFields() : void
    {
        $schema = BuildSchema::build('
      type Query {
        field(arg: SomeInputObject): String
      }

      input SomeInputObject
        ');
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Input Object type SomeInputObject must define one or more fields.',
                'locations' => [['line' => 6, 'column' => 7]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects an Input Object type with incorrectly typed fields')
     */
    public function testRejectsAnInputObjectTypeWithIncorrectlyTypedFields() : void
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
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [
                [
                    'message'   => 'The type of SomeInputObject.badObject must be Input Type but got: SomeObject.',
                    'locations' => [['line' => 13, 'column' => 20]],
                ],
                [
                    'message'   => 'The type of SomeInputObject.badUnion must be Input Type but got: SomeUnion.',
                    'locations' => [['line' => 14, 'column' => 19]],
                ],
            ]
        );
    }

    /**
     * @see it('rejects an Enum type without values')
     */
    public function testRejectsAnEnumTypeWithoutValues() : void
    {
        $schema = BuildSchema::build('
      type Query {
        field: SomeEnum
      }
      
      enum SomeEnum
        ');
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Enum type SomeEnum must define one or more values.',
                'locations' => [['line' => 6, 'column' => 7]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects an Enum type with duplicate values')
     */
    public function testRejectsAnEnumTypeWithDuplicateValues() : void
    {
        $schema = BuildSchema::build('
      type Query {
        field: SomeEnum
      }
      
      enum SomeEnum {
        SOME_VALUE
        SOME_VALUE
      }
        ');
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Enum type SomeEnum can include value SOME_VALUE only once.',
                'locations' => [['line' => 7, 'column' => 9], ['line' => 8, 'column' => 9]],
            ],
            ]
        );
    }

    public function testDoesNotAllowIsDeprecatedWithoutDeprecationReasonOnEnum() : void
    {
        $enum = new EnumType([
            'name'   => 'SomeEnum',
            'values' => [
                'value' => ['isDeprecated' => true],
            ],
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('SomeEnum.value should provide "deprecationReason" instead of "isDeprecated".');
        $enum->assertValid();
    }

    /**
     * DESCRIBE: Type System: Object fields must have output types
     *
     * @return string[][]
     */
    public function invalidEnumValueName() : array
    {
        return [
            ['#value', 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "#value" does not.'],
            ['1value', 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "1value" does not.'],
            ['KEBAB-CASE', 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "KEBAB-CASE" does not.'],
            ['false', 'Enum type SomeEnum cannot include value: false.'],
            ['true', 'Enum type SomeEnum cannot include value: true.'],
            ['null', 'Enum type SomeEnum cannot include value: null.'],
        ];
    }

    /**
     * @see          it('rejects an Enum type with incorrectly named values')
     *
     * @dataProvider invalidEnumValueName
     */
    public function testRejectsAnEnumTypeWithIncorrectlyNamedValues($name, $expectedMessage) : void
    {
        $schema = $this->schemaWithEnum($name);

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [['message' => $expectedMessage],
            ]
        );
    }

    private function schemaWithEnum($name)
    {
        return $this->schemaWithFieldType(
            new EnumType([
                'name'   => 'SomeEnum',
                'values' => [
                    $name => [],
                ],
            ])
        );
    }

    /**
     * @see it('accepts an output type as an Object field type')
     */
    public function testAcceptsAnOutputTypeAsNnObjectFieldType() : void
    {
        foreach ($this->outputTypes as $type) {
            $schema = $this->schemaWithObjectFieldOfType($type);
            self::assertEquals([], $schema->validate());
        }
    }

    /**
     * DESCRIBE: Type System: Objects can only implement unique interfaces
     */
    private function schemaWithObjectFieldOfType($fieldType) : Schema
    {
        $BadObjectType = new ObjectType([
            'name'   => 'BadObject',
            'fields' => [
                'badField' => ['type' => $fieldType],
            ],
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'f' => ['type' => $BadObjectType],
                ],
            ]),
            'types' => [$this->SomeObjectType],
        ]);
    }

    /**
     * @see it('rejects an empty Object field type')
     */
    public function testRejectsAnEmptyObjectFieldType() : void
    {
        $schema = $this->schemaWithObjectFieldOfType(null);

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [['message' => 'The type of BadObject.badField must be Output Type but got: null.'],
            ]
        );
    }

    /**
     * @see it('rejects a non-output type as an Object field type')
     */
    public function testRejectsANonOutputTypeAsAnObjectFieldType() : void
    {
        foreach ($this->notOutputTypes as $type) {
            $schema = $this->schemaWithObjectFieldOfType($type);

            $this->assertContainsValidationMessage(
                $schema->validate(),
                [[
                    'message' => 'The type of BadObject.badField must be Output Type but got: ' . Utils::printSafe($type) . '.',
                ],
                ]
            );
        }
    }

    /**
     * @see it('rejects with relevant locations for a non-output type as an Object field type')
     */
    public function testRejectsWithReleventLocationsForANonOutputTypeAsAnObjectFieldType() : void
    {
        $schema = BuildSchema::build('
      type Query {
        field: [SomeInputObject]
      }
      
      input SomeInputObject {
        field: String
      }
        ');
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'The type of Query.field must be Output Type but got: [SomeInputObject].',
                'locations' => [['line' => 3, 'column' => 16]],
            ],
            ]
        );
    }

    // DESCRIBE: Type System: Interface fields must have output types

    /**
     * @see it('rejects an Object implementing a non-type values')
     */
    public function testRejectsAnObjectImplementingANonTypeValues() : void
    {
        $schema   = new Schema([
            'query' => new ObjectType([
                'name'       => 'BadObject',
                'interfaces' => [null],
                'fields'     => ['a' => Type::string()],
            ]),
        ]);
        $expected = ['message' => 'Type BadObject must only implement Interface types, it cannot implement null.'];

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [$expected]
        );
    }

    /**
     * @see it('rejects an Object implementing a non-Interface type')
     */
    public function testRejectsAnObjectImplementingANonInterfaceType() : void
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
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Type BadObject must only implement Interface types, it cannot implement SomeInputObject.',
                'locations' => [['line' => 10, 'column' => 33]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects an Object implementing the same interface twice')
     */
    public function testRejectsAnObjectImplementingTheSameInterfaceTwice() : void
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
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Type AnotherObject can only implement AnotherInterface once.',
                'locations' => [['line' => 10, 'column' => 37], ['line' => 10, 'column' => 56]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects an Object implementing the same interface twice due to extension')
     */
    public function testRejectsAnObjectImplementingTheSameInterfaceTwiceDueToExtension() : void
    {
        $this->expectNotToPerformAssertions();
        $this->markTestIncomplete('extend does not work this way (yet).');
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
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Type AnotherObject can only implement AnotherInterface once.',
                'locations' => [['line' => 10, 'column' => 37], ['line' => 14, 'column' => 38]],
            ],
            ]
        );
    }

    // DESCRIBE: Type System: Field arguments must have input types

    /**
     * @see it('accepts an output type as an Interface field type')
     */
    public function testAcceptsAnOutputTypeAsAnInterfaceFieldType() : void
    {
        foreach ($this->outputTypes as $type) {
            $schema = $this->schemaWithInterfaceFieldOfType($type);
            self::assertEquals([], $schema->validate());
        }
    }

    private function schemaWithInterfaceFieldOfType($fieldType)
    {
        $BadInterfaceType = new InterfaceType([
            'name'   => 'BadInterface',
            'fields' => [
                'badField' => ['type' => $fieldType],
            ],
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'f' => ['type' => $BadInterfaceType],
                ],
            ]),
        ]);
    }

    /**
     * @see it('rejects an empty Interface field type')
     */
    public function testRejectsAnEmptyInterfaceFieldType() : void
    {
        $schema = $this->schemaWithInterfaceFieldOfType(null);
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [['message' => 'The type of BadInterface.badField must be Output Type but got: null.'],
            ]
        );
    }

    /**
     * @see it('rejects a non-output type as an Interface field type')
     */
    public function testRejectsANonOutputTypeAsAnInterfaceFieldType() : void
    {
        foreach ($this->notOutputTypes as $type) {
            $schema = $this->schemaWithInterfaceFieldOfType($type);

            $this->assertContainsValidationMessage(
                $schema->validate(),
                [[
                    'message' => 'The type of BadInterface.badField must be Output Type but got: ' . Utils::printSafe($type) . '.',
                ],
                ]
            );
        }
    }

    // DESCRIBE: Type System: Input Object fields must have input types

    /**
     * @see it('rejects a non-output type as an Interface field type with locations')
     */
    public function testRejectsANonOutputTypeAsAnInterfaceFieldTypeWithLocations() : void
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
        ');
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'The type of SomeInterface.field must be Output Type but got: SomeInputObject.',
                'locations' => [['line' => 7, 'column' => 16]],
            ],
            ]
        );
    }

    /**
     * @see it('accepts an input type as a field arg type')
     */
    public function testAcceptsAnInputTypeAsAFieldArgType() : void
    {
        foreach ($this->inputTypes as $type) {
            $schema = $this->schemaWithArgOfType($type);
            self::assertEquals([], $schema->validate());
        }
    }

    private function schemaWithArgOfType($argType)
    {
        $BadObjectType = new ObjectType([
            'name'   => 'BadObject',
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
                'name'   => 'Query',
                'fields' => [
                    'f' => ['type' => $BadObjectType],
                ],
            ]),
        ]);
    }

    /**
     * @see it('rejects an empty field arg type')
     */
    public function testRejectsAnEmptyFieldArgType() : void
    {
        $schema = $this->schemaWithArgOfType(null);
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [['message' => 'The type of BadObject.badField(badArg:) must be Input Type but got: null.'],
            ]
        );
    }

    // DESCRIBE: Objects must adhere to Interface they implement

    /**
     * @see it('rejects a non-input type as a field arg type')
     */
    public function testRejectsANonInputTypeAsAFieldArgType() : void
    {
        foreach ($this->notInputTypes as $type) {
            $schema = $this->schemaWithArgOfType($type);
            $this->assertContainsValidationMessage(
                $schema->validate(),
                [[
                    'message' => 'The type of BadObject.badField(badArg:) must be Input Type but got: ' . Utils::printSafe($type) . '.',
                ],
                ]
            );
        }
    }

    /**
     * @see it('rejects a non-input type as a field arg with locations')
     */
    public function testANonInputTypeAsAFieldArgWithLocations() : void
    {
        $schema = BuildSchema::build('
      type Query {
        test(arg: SomeObject): String
      }
      
      type SomeObject {
        foo: String
      }
        ');
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'The type of Query.test(arg:) must be Input Type but got: SomeObject.',
                'locations' => [['line' => 3, 'column' => 19]],
            ],
            ]
        );
    }

    /**
     * @see it('accepts an input type as an input field type')
     */
    public function testAcceptsAnInputTypeAsAnInputFieldType() : void
    {
        foreach ($this->inputTypes as $type) {
            $schema = $this->schemaWithInputFieldOfType($type);
            self::assertEquals([], $schema->validate());
        }
    }

    private function schemaWithInputFieldOfType($inputFieldType)
    {
        $BadInputObjectType = new InputObjectType([
            'name'   => 'BadInputObject',
            'fields' => [
                'badField' => ['type' => $inputFieldType],
            ],
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'f' => [
                        'type' => Type::string(),
                        'args' => [
                            'badArg' => ['type' => $BadInputObjectType],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    /**
     * @see it('rejects an empty input field type')
     */
    public function testRejectsAnEmptyInputFieldType() : void
    {
        $schema = $this->schemaWithInputFieldOfType(null);
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [['message' => 'The type of BadInputObject.badField must be Input Type but got: null.'],
            ]
        );
    }

    /**
     * @see it('rejects a non-input type as an input field type')
     */
    public function testRejectsANonInputTypeAsAnInputFieldType() : void
    {
        foreach ($this->notInputTypes as $type) {
            $schema = $this->schemaWithInputFieldOfType($type);
            $this->assertContainsValidationMessage(
                $schema->validate(),
                [[
                    'message' => 'The type of BadInputObject.badField must be Input Type but got: ' . Utils::printSafe($type) . '.',
                ],
                ]
            );
        }
    }

    /**
     * @see it('rejects a non-input type as an input object field with locations')
     */
    public function testRejectsANonInputTypeAsAnInputObjectFieldWithLocations() : void
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
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'The type of SomeInputObject.foo must be Input Type but got: SomeObject.',
                'locations' => [['line' => 7, 'column' => 14]],
            ],
            ]
        );
    }

    /**
     * @see it('accepts an Object which implements an Interface')
     */
    public function testAcceptsAnObjectWhichImplementsAnInterface() : void
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

        self::assertEquals(
            [],
            $schema->validate()
        );
    }

    /**
     * @see it('accepts an Object which implements an Interface along with more fields')
     */
    public function testAcceptsAnObjectWhichImplementsAnInterfaceAlongWithMoreFields() : void
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

        self::assertEquals(
            [],
            $schema->validate()
        );
    }

    /**
     * @see it('accepts an Object which implements an Interface field along with additional optional arguments')
     */
    public function testAcceptsAnObjectWhichImplementsAnInterfaceFieldAlongWithAdditionalOptionalArguments() : void
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

        self::assertEquals(
            [],
            $schema->validate()
        );
    }

    /**
     * @see it('rejects an Object missing an Interface field')
     */
    public function testRejectsAnObjectMissingAnInterfaceField() : void
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

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Interface field AnotherInterface.field expected but ' .
                    'AnotherObject does not provide it.',
                'locations' => [['line' => 7, 'column' => 9], ['line' => 10, 'column' => 7]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects an Object with an incorrectly typed Interface field')
     */
    public function testRejectsAnObjectWithAnIncorrectlyTypedInterfaceField() : void
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

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Interface field AnotherInterface.field expects type String but ' .
                    'AnotherObject.field is type Int.',
                'locations' => [['line' => 7, 'column' => 31], ['line' => 11, 'column' => 31]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects an Object with a differently typed Interface field')
     */
    public function testRejectsAnObjectWithADifferentlyTypedInterfaceField() : void
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

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Interface field AnotherInterface.field expects type A but ' .
                    'AnotherObject.field is type B.',
                'locations' => [['line' => 10, 'column' => 16], ['line' => 14, 'column' => 16]],
            ],
            ]
        );
    }

    /**
     * @see it('accepts an Object with a subtyped Interface field (interface)')
     */
    public function testAcceptsAnObjectWithASubtypedInterfaceFieldForInterface() : void
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

        self::assertEquals([], $schema->validate());
    }

    /**
     * @see it('accepts an Object with a subtyped Interface field (union)')
     */
    public function testAcceptsAnObjectWithASubtypedInterfaceFieldForUnion() : void
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

        self::assertEquals([], $schema->validate());
    }

    /**
     * @see it('rejects an Object missing an Interface argument')
     */
    public function testRejectsAnObjectMissingAnInterfaceArgument() : void
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

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Interface field argument AnotherInterface.field(input:) expected ' .
                    'but AnotherObject.field does not provide it.',
                'locations' => [['line' => 7, 'column' => 15], ['line' => 11, 'column' => 9]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects an Object with an incorrectly typed Interface argument')
     */
    public function testRejectsAnObjectWithAnIncorrectlyTypedInterfaceArgument() : void
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

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Interface field argument AnotherInterface.field(input:) expects ' .
                    'type String but AnotherObject.field(input:) is type Int.',
                'locations' => [['line' => 7, 'column' => 22], ['line' => 11, 'column' => 22]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects an Object with both an incorrectly typed field and argument')
     */
    public function testRejectsAnObjectWithBothAnIncorrectlyTypedFieldAndArgument() : void
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

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [
                [
                    'message'   => 'Interface field AnotherInterface.field expects type String but ' .
                        'AnotherObject.field is type Int.',
                    'locations' => [['line' => 7, 'column' => 31], ['line' => 11, 'column' => 28]],
                ],
                [
                    'message'   => 'Interface field argument AnotherInterface.field(input:) expects ' .
                        'type String but AnotherObject.field(input:) is type Int.',
                    'locations' => [['line' => 7, 'column' => 22], ['line' => 11, 'column' => 22]],
                ],
            ]
        );
    }

    /**
     * @see it('rejects an Object which implements an Interface field along with additional required arguments')
     */
    public function testRejectsAnObjectWhichImplementsAnInterfaceFieldAlongWithAdditionalRequiredArguments() : void
    {
        $schema = BuildSchema::build('
      type Query {
        test: AnotherObject
      }

      interface AnotherInterface {
        field(input: String): String
      }

      type AnotherObject implements AnotherInterface {
        field(input: String, anotherInput: String!): String
      }
        ');

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Object field argument AnotherObject.field(anotherInput:) is of ' .
                    'required type String! but is not also provided by the Interface ' .
                    'field AnotherInterface.field.',
                'locations' => [['line' => 11, 'column' => 44], ['line' => 7, 'column' => 9]],
            ],
            ]
        );
    }

    /**
     * @see it('accepts an Object with an equivalently wrapped Interface field type')
     */
    public function testAcceptsAnObjectWithAnEquivalentlyWrappedInterfaceFieldType() : void
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

        self::assertEquals([], $schema->validate());
    }

    /**
     * @see it('rejects an Object with a non-list Interface field list type')
     */
    public function testRejectsAnObjectWithANonListInterfaceFieldListType() : void
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

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Interface field AnotherInterface.field expects type [String] ' .
                    'but AnotherObject.field is type String.',
                'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
            ],
            ]
        );
    }

    /**
     * @see it('rejects an Object with a list Interface field non-list type')
     */
    public function testRejectsAnObjectWithAListInterfaceFieldNonListType() : void
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

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Interface field AnotherInterface.field expects type String but ' .
                    'AnotherObject.field is type [String].',
                'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
            ],
            ]
        );
    }

    /**
     * @see it('accepts an Object with a subset non-null Interface field type')
     */
    public function testAcceptsAnObjectWithASubsetNonNullInterfaceFieldType() : void
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

        self::assertEquals([], $schema->validate());
    }

    /**
     * @see it('rejects an Object with a superset nullable Interface field type')
     */
    public function testRejectsAnObjectWithASupersetNullableInterfaceFieldType() : void
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

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message'   => 'Interface field AnotherInterface.field expects type String! ' .
                    'but AnotherObject.field is type String.',
                'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
            ],
            ]
        );
    }

    public function testRejectsDifferentInstancesOfTheSameType() : void
    {
        // Invalid: always creates new instance vs returning one from registry
        $typeLoader = static function ($name) {
            switch ($name) {
                case 'Query':
                    return new ObjectType([
                        'name'   => 'Query',
                        'fields' => [
                            'test' => Type::string(),
                        ],
                    ]);
                default:
                    return null;
            }
        };

        $schema = new Schema([
            'query'      => $typeLoader('Query'),
            'typeLoader' => $typeLoader,
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Type loader returns different instance for Query than field/argument definitions. ' .
            'Make sure you always return the same instance for the same type name.'
        );
        $schema->assertValid();
    }
}
