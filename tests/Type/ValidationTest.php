<?php
namespace GraphQL\Tests\Type;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public $SomeScalarType;

    public $SomeObjectType;

    public $SomeUnionType;

    public $SomeInterfaceType;

    public $SomeEnumType;

    public $SomeInputObjectType;

    public $outputTypes;

    public $notOutputTypes;

    public $inputTypes;

    public $notInputTypes;

    public $Number;

    public function setUp()
    {
        $this->Number = 1;

        $this->SomeScalarType = new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => function() {},
            'parseValue' => function() {},
            'parseLiteral' => function() {}
        ]);

        $this->SomeObjectType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [ 'f' => [ 'type' => Type::string() ] ],
            'interfaces' => function() {return [$this->SomeInterfaceType];}
        ]);
        $this->SomeUnionType = new UnionType([
            'name' => 'SomeUnion',
            'types' => [ $this->SomeObjectType ]
        ]);

        $this->SomeInterfaceType = new InterfaceType([
            'name' => 'SomeInterface',
            'fields' => [ 'f' => ['type' => Type::string() ]]
        ]);

        $this->SomeEnumType = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'ONLY' => []
            ]
        ]);

        $this->SomeInputObjectType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'val' => [ 'type' => Type::string(), 'defaultValue' => 'hello' ]
            ]
        ]);

        $this->outputTypes = $this->withModifiers([
            Type::string(),
            $this->SomeScalarType,
            $this->SomeEnumType,
            $this->SomeObjectType,
            $this->SomeUnionType,
            $this->SomeInterfaceType
        ]);

        $this->notOutputTypes = $this->withModifiers([
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

    public function tearDown()
    {
        parent::tearDown();
        Warning::enable(Warning::WARNING_NOT_A_TYPE);
    }

    /**
     * @param InvariantViolation[]|Error[] $array
     * @param array $messages
     */
    private function assertContainsValidationMessage($array, $messages) {
        $this->assertCount(
            count($messages),
            $array,
            'For messages: ' . $messages[0]['message'] . "\n" .
            "Received: \n" . join("\n", array_map(function($error) { return $error->getMessage(); }, $array))
        );
        foreach ($array as $index => $error) {
            if(!isset($messages[$index]) || !$error instanceof Error) {
                $this->fail('Received unexpected error: ' . $error->getMessage());
            }
            $this->assertEquals($messages[$index]['message'], $error->getMessage());
            $errorLocations = [];
            foreach ($error->getLocations() as $location) {
                $errorLocations[] = $location->toArray();
            }
            $this->assertEquals(
                isset($messages[$index]['locations']) ? $messages[$index]['locations'] : [],
                $errorLocations
            );
        }
    }

    public function testRejectsTypesWithoutNames()
    {
        $this->assertEachCallableThrows([
            function() {
                return new ObjectType([]);
            },
            function() {
                return new EnumType([]);
            },
            function() {
                return new InputObjectType([]);
            },
            function() {
                return new UnionType([]);
            },
            function() {
                return new InterfaceType([]);
            }
        ], 'Must provide name.');
    }

    // DESCRIBE: Type System: A Schema must have Object root types

    /**
     * @it accepts a Schema whose query type is an object type
     */
    public function testAcceptsASchemaWhoseQueryTypeIsAnObjectType()
    {
        $schema = BuildSchema::build('
      type Query {
        test: String
      }
        ');
        $this->assertEquals([], $schema->validate());

        $schemaWithDef = BuildSchema::build('
      schema {
        query: QueryRoot
      }
      type QueryRoot {
        test: String
      }
    ');
        $this->assertEquals([], $schemaWithDef->validate());
    }

    /**
     * @it accepts a Schema whose query and mutation types are object types
     */
    public function testAcceptsASchemaWhoseQueryAndMutationTypesAreObjectTypes()
    {
        $schema = BuildSchema::build('
      type Query {
        test: String
      }

      type Mutation {
        test: String
      }
        ');
        $this->assertEquals([], $schema->validate());

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
        $this->assertEquals([], $schema->validate());
    }

    /**
     * @it accepts a Schema whose query and subscription types are object types
     */
    public function testAcceptsASchemaWhoseQueryAndSubscriptionTypesAreObjectTypes()
    {
        $schema = BuildSchema::build('
      type Query {
        test: String
      }

      type Subscription {
        test: String
      }
        ');
        $this->assertEquals([], $schema->validate());

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
        $this->assertEquals([], $schema->validate());
    }

    /**
     * @it rejects a Schema without a query type
     */
    public function testRejectsASchemaWithoutAQueryType()
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
                'message' => 'Query root type must be provided.',
                'locations' => [['line' => 2, 'column' => 7]],
            ]]
        );
    }

    /**
     * @it rejects a Schema whose query root type is not an Object type
     */
    public function testRejectsASchemaWhoseQueryTypeIsNotAnObjectType()
    {
        $schema = BuildSchema::build('
      input Query {
        test: String
      }
        ');

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message' => 'Query root type must be Object type, it cannot be Query.',
                'locations' => [['line' => 2, 'column' => 7]],
            ]]
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
                'message' => 'Query root type must be Object type, it cannot be SomeInputObject.',
                'locations' => [['line' => 3, 'column' => 16]],
            ]]
        );
    }

    /**
     * @it rejects a Schema whose mutation type is an input type
     */
    public function testRejectsASchemaWhoseMutationTypeIsAnInputType()
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
                'message' => 'Mutation root type must be Object type if provided, it cannot be Mutation.',
                'locations' => [['line' => 6, 'column' => 7]],
            ]]
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
                'message' => 'Mutation root type must be Object type if provided, it cannot be SomeInputObject.',
                'locations' => [['line' => 4, 'column' => 19]],
            ]]
        );
    }

    /**
     * @it rejects a Schema whose subscription type is an input type
     */
    public function testRejectsASchemaWhoseSubscriptionTypeIsAnInputType()
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
                'message' => 'Subscription root type must be Object type if provided, it cannot be Subscription.',
                'locations' => [['line' => 6, 'column' => 7]],
            ]]
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
                'message' => 'Subscription root type must be Object type if provided, it cannot be SomeInputObject.',
                'locations' => [['line' => 4, 'column' => 23]],
            ]]
        );


    }

    /**
     * @it rejects a Schema whose directives are incorrectly typed
     */
    public function testRejectsASchemaWhoseDirectivesAreIncorrectlyTyped()
    {
        $schema = new Schema([
            'query' => $this->SomeObjectType,
            'directives' => ['somedirective']
        ]);

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [['message' => 'Expected directive but got: somedirective.']]
        );
    }

    // DESCRIBE: Type System: Objects must have fields

    /**
     * @it accepts an Object type with fields object
     */
    public function testAcceptsAnObjectTypeWithFieldsObject()
    {
        $schema = BuildSchema::build('
      type Query {
        field: SomeObject
      }

      type SomeObject {
        field: String
      }
        ');

        $this->assertEquals([], $schema->validate());
    }

    /**
     * @it rejects an Object type with missing fields
     */
    public function testRejectsAnObjectTypeWithMissingFields()
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
                'message' => 'Type IncompleteObject must define one or more fields.',
                'locations' => [['line' => 6, 'column' => 7]],
            ]]
        );

        $manualSchema = $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'IncompleteObject',
                'fields' => [],
            ])
        );

        $this->assertContainsValidationMessage(
            $manualSchema->validate(),
            [['message' => 'Type IncompleteObject must define one or more fields.']]
        );

        $manualSchema2 = $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'IncompleteObject',
                'fields' => function () { return []; },
            ])
        );

        $this->assertContainsValidationMessage(
            $manualSchema2->validate(),
            [['message' => 'Type IncompleteObject must define one or more fields.']]
        );
    }

    /**
     * @it rejects an Object type with incorrectly named fields
     */
    public function testRejectsAnObjectTypeWithIncorrectlyNamedFields()
    {
        $schema = $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'SomeObject',
                'fields' => [
                  'bad-name-with-dashes' => ['type' => Type::string()]
                ],
            ])
        );

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message' => 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but ' .
                              '"bad-name-with-dashes" does not.',
            ]]
        );
    }

    public function testAcceptsShorthandNotationForFields()
    {
        $this->expectNotToPerformAssertions();
        $schema = $this->schemaWithFieldType(
            new ObjectType([
                'name' => 'SomeObject',
                'fields' => [
                    'field' => Type::string()
                ]
            ])
        );
        $schema->assertValid();
    }

    // DESCRIBE: Type System: Fields args must be properly named

    /**
     * @it accepts field args with valid names
     */
    public function testAcceptsFieldArgsWithValidNames()
    {
        $schema = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'goodField' => [
                    'type' => Type::string(),
                    'args' => [
                        'goodArg' => ['type' => Type::string()]
                    ]
                ]
            ]
        ]));
        $this->assertEquals([], $schema->validate());
    }

    /**
     * @it rejects field arg with invalid names
     */
    public function testRejectsFieldArgWithInvalidNames()
    {
        $QueryType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'badField' => [
                    'type' => Type::string(),
                    'args' => [
                        'bad-name-with-dashes' => ['type' => Type::string()]
                    ]
                ]
            ]
        ]);
        $schema = new Schema(['query' => $QueryType]);

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [['message' => 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "bad-name-with-dashes" does not.']]
        );
    }

    // DESCRIBE: Type System: Union types must be valid

    /**
     * @it accepts a Union type with member types
     */
    public function testAcceptsAUnionTypeWithArrayTypes()
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

        $this->assertEquals([], $schema->validate());
    }

    /**
     * @it rejects a Union type with empty types
     */
    public function testRejectsAUnionTypeWithEmptyTypes()
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
                'message' => 'Union type BadUnion must define one or more member types.',
                'locations' => [['line' => 6, 'column' => 7]],
            ]]
        );
    }

    /**
     * @it rejects a Union type with duplicated member type
     */
    public function testRejectsAUnionTypeWithDuplicatedMemberType()
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
                'message' => 'Union type BadUnion can only include type TypeA once.',
                'locations' => [['line' => 15, 'column' => 11], ['line' => 17, 'column' => 11]],
            ]]
        );
    }

    /**
     * @it rejects a Union type with non-Object members types
     */
    public function testRejectsAUnionTypeWithNonObjectMembersType()
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
                'message' => 'Union type BadUnion can only include Object types, ' .
                             'it cannot include String.',
                'locations' => [['line' => 16, 'column' => 11]],
            ]]

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

        foreach($badUnionMemberTypes as $memberType) {
            $badSchema = $this->schemaWithFieldType(
                new UnionType(['name' => 'BadUnion', 'types' => [$memberType]])
            );
            $this->assertContainsValidationMessage(
                $badSchema->validate(),
                [[
                    'message' => 'Union type BadUnion can only include Object types, ' .
                                 "it cannot include ". Utils::printSafe($memberType) . ".",
                ]]
            );
        }
    }

    // DESCRIBE: Type System: Input Objects must have fields

    /**
     * @it accepts an Input Object type with fields
     */
    public function testAcceptsAnInputObjectTypeWithFields()
    {
        $schema = BuildSchema::build('
      type Query {
        field(arg: SomeInputObject): String
      }

      input SomeInputObject {
        field: String
      }
        ');
        $this->assertEquals([], $schema->validate());
    }

    /**
     * @it rejects an Input Object type with missing fields
     */
    public function testRejectsAnInputObjectTypeWithMissingFields()
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
                'message' => 'Input Object type SomeInputObject must define one or more fields.',
                'locations' => [['line' => 6, 'column' => 7]],
            ]]
        );
    }

    /**
     * @it rejects an Input Object type with incorrectly typed fields
     */
    public function testRejectsAnInputObjectTypeWithIncorrectlyTypedFields()
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
            [[
                'message' => 'The type of SomeInputObject.badObject must be Input Type but got: SomeObject.',
                'locations' => [['line' => 13, 'column' => 20]],
            ],[
                'message' => 'The type of SomeInputObject.badUnion must be Input Type but got: SomeUnion.',
                'locations' => [['line' => 14, 'column' => 19]],
            ]]
        );
    }

    // DESCRIBE: Type System: Enum types must be well defined

    /**
     * @it rejects an Enum type without values
     */
    public function testRejectsAnEnumTypeWithoutValues()
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
                'message' => 'Enum type SomeEnum must define one or more values.',
                'locations' => [['line' => 6, 'column' => 7]],
            ]]
        );
    }

    /**
     * @it rejects an Enum type with duplicate values
     */
    public function testRejectsAnEnumTypeWithDuplicateValues()
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
                'message' => 'Enum type SomeEnum can include value SOME_VALUE only once.',
                'locations' => [['line' => 7, 'column' => 9], ['line' => 8, 'column' => 9]],
            ]]
        );
    }

    public function testDoesNotAllowIsDeprecatedWithoutDeprecationReasonOnEnum()
    {
        $enum = new EnumType([
            'name' => 'SomeEnum',
            'values' => [
                'value' => ['isDeprecated' => true]
            ]
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('SomeEnum.value should provide "deprecationReason" instead of "isDeprecated".');
        $enum->assertValid();
    }

    private function schemaWithEnum($name)
    {
        return $this->schemaWithFieldType(
            new EnumType([
                'name' => 'SomeEnum',
                'values' => [
                    $name => []
                ]
            ])
        );
    }

    public function invalidEnumValueName()
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
     * @it rejects an Enum type with incorrectly named values
     * @dataProvider invalidEnumValueName
     */
    public function testRejectsAnEnumTypeWithIncorrectlyNamedValues($name, $expectedMessage)
    {
        $schema = $this->schemaWithEnum($name);

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message' => $expectedMessage,
            ]]
        );
    }

    // DESCRIBE: Type System: Object fields must have output types

    /**
     * @it accepts an output type as an Object field type
     */
    public function testAcceptsAnOutputTypeAsNnObjectFieldType()
    {
        foreach ($this->outputTypes as $type) {
            $schema = $this->schemaWithObjectFieldOfType($type);
            $this->assertEquals([], $schema->validate());
        }
    }

    /**
     * @it rejects an empty Object field type
     */
    public function testRejectsAnEmptyObjectFieldType()
    {
        $schema = $this->schemaWithObjectFieldOfType(null);

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message' => 'The type of BadObject.badField must be Output Type but got: null.',
            ]]
        );
    }

    /**
     * @it rejects a non-output type as an Object field type
     */
    public function testRejectsANonOutputTypeAsAnObjectFieldType()
    {
        foreach ($this->notOutputTypes as $type) {
            $schema = $this->schemaWithObjectFieldOfType($type);

            $this->assertContainsValidationMessage(
                $schema->validate(),
                [[
                    'message' => 'The type of BadObject.badField must be Output Type but got: ' . Utils::printSafe($type) . '.',
                ]]
            );
        }
    }

    /**
     * @it rejects with relevant locations for a non-output type as an Object field type
     */
    public function testRejectsWithReleventLocationsForANonOutputTypeAsAnObjectFieldType()
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
                'message' => 'The type of Query.field must be Output Type but got: [SomeInputObject].',
                'locations' => [['line' => 3, 'column' => 16]],
            ]]
        );
    }

    // DESCRIBE: Type System: Objects can only implement unique interfaces

    /**
     * @it rejects an Object implementing a non-type values
     */
    public function testRejectsAnObjectImplementingANonTypeValues()
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'BadObject',
                'interfaces' => [null],
                'fields' => ['a' => Type::string()]
            ]),
        ]);
        $expected = [
            'message' => 'Type BadObject must only implement Interface types, it cannot implement null.'
        ];

        $this->assertContainsValidationMessage(
            $schema->validate(),
            [$expected]
        );
    }

    /**
     * @it rejects an Object implementing a non-Interface type
     */
    public function testRejectsAnObjectImplementingANonInterfaceType()
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
                'message' => 'Type BadObject must only implement Interface types, it cannot implement SomeInputObject.',
                'locations' => [['line' => 10, 'column' => 33]],
            ]]
        );
    }

    /**
     * @it rejects an Object implementing the same interface twice
     */
    public function testRejectsAnObjectImplementingTheSameInterfaceTwice()
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
                'message' => 'Type AnotherObject can only implement AnotherInterface once.',
                'locations' => [['line' => 10, 'column' => 37], ['line' => 10, 'column' => 56]],
            ]]
        );
    }

    /**
     * @it rejects an Object implementing the same interface twice due to extension
     */
    public function testRejectsAnObjectImplementingTheSameInterfaceTwiceDueToExtension()
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
                'message' => 'Type AnotherObject can only implement AnotherInterface once.',
                'locations' => [['line' => 10, 'column' => 37], ['line' => 14, 'column' => 38]],
            ]]
        );
    }

    // DESCRIBE: Type System: Interface fields must have output types

    /**
     * @it accepts an output type as an Interface field type
     */
    public function testAcceptsAnOutputTypeAsAnInterfaceFieldType()
    {
        foreach ($this->outputTypes as $type) {
            $schema = $this->schemaWithInterfaceFieldOfType($type);
            $this->assertEquals([], $schema->validate());
        }
    }

    /**
     * @it rejects an empty Interface field type
     */
    public function testRejectsAnEmptyInterfaceFieldType()
    {
        $schema = $this->schemaWithInterfaceFieldOfType(null);
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message' => 'The type of BadInterface.badField must be Output Type but got: null.',
            ]]
        );
    }

    /**
     * @it rejects a non-output type as an Interface field type
     */
    public function testRejectsANonOutputTypeAsAnInterfaceFieldType()
    {
        foreach ($this->notOutputTypes as $type) {
            $schema = $this->schemaWithInterfaceFieldOfType($type);

            $this->assertContainsValidationMessage(
                $schema->validate(),
                [[
                    'message' => 'The type of BadInterface.badField must be Output Type but got: ' . Utils::printSafe($type) . '.',
                ]]
            );
        }
    }

    /**
     * @it rejects a non-output type as an Interface field type with locations
     */
    public function testRejectsANonOutputTypeAsAnInterfaceFieldTypeWithLocations()
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
                'message' => 'The type of SomeInterface.field must be Output Type but got: SomeInputObject.',
                'locations' => [['line' => 7, 'column' => 16]],
            ]]
        );
    }

    // DESCRIBE: Type System: Field arguments must have input types

    /**
     * @it accepts an input type as a field arg type
     */
    public function testAcceptsAnInputTypeAsAFieldArgType()
    {
        foreach ($this->inputTypes as $type) {
            $schema = $this->schemaWithArgOfType($type);
            $this->assertEquals([], $schema->validate());
        }
    }

    /**
     * @it rejects an empty field arg type
     */
    public function testRejectsAnEmptyFieldArgType()
    {
        $schema = $this->schemaWithArgOfType(null);
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message' => 'The type of BadObject.badField(badArg:) must be Input Type but got: null.',
            ]]
        );
    }

    /**
     * @it rejects a non-input type as a field arg type
     */
    public function testRejectsANonInputTypeAsAFieldArgType()
    {
        foreach ($this->notInputTypes as $type) {
            $schema = $this->schemaWithArgOfType($type);
            $this->assertContainsValidationMessage(
                $schema->validate(),
                [[
                    'message' => 'The type of BadObject.badField(badArg:) must be Input Type but got: ' . Utils::printSafe($type) . '.',
                ]]
            );
        }
    }

    /**
     * @it rejects a non-input type as a field arg with locations
     */
    public function testANonInputTypeAsAFieldArgWithLocations()
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
                'message' => 'The type of Query.test(arg:) must be Input Type but got: SomeObject.',
                'locations' => [['line' => 3, 'column' => 19]],
            ]]
        );
    }

    // DESCRIBE: Type System: Input Object fields must have input types

    /**
     * @it accepts an input type as an input field type
     */
    public function testAcceptsAnInputTypeAsAnInputFieldType()
    {
        foreach ($this->inputTypes as $type) {
            $schema = $this->schemaWithInputFieldOfType($type);
            $this->assertEquals([], $schema->validate());
        }
    }

    /**
     * @it rejects an empty input field type
     */
    public function testRejectsAnEmptyInputFieldType()
    {
        $schema = $this->schemaWithInputFieldOfType(null);
        $this->assertContainsValidationMessage(
            $schema->validate(),
            [[
                'message' => 'The type of BadInputObject.badField must be Input Type but got: null.',
            ]]
        );
    }

    /**
     * @it rejects a non-input type as an input field type
     */
    public function testRejectsANonInputTypeAsAnInputFieldType()
    {
        foreach ($this->notInputTypes as $type) {
            $schema = $this->schemaWithInputFieldOfType($type);
            $this->assertContainsValidationMessage(
                $schema->validate(),
                [[
                    'message' => 'The type of BadInputObject.badField must be Input Type but got: ' . Utils::printSafe($type) . '.',
                ]]
            );
        }
    }

    /**
     * @it rejects a non-input type as an input object field with locations
     */
    public function testRejectsANonInputTypeAsAnInputObjectFieldWithLocations()
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
                'message' => 'The type of SomeInputObject.foo must be Input Type but got: SomeObject.',
                'locations' => [['line' => 7, 'column' => 14]],
            ]]
        );
    }

    // DESCRIBE: Objects must adhere to Interface they implement

    /**
     * @it accepts an Object which implements an Interface
     */
    public function testAcceptsAnObjectWhichImplementsAnInterface()
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

        $this->assertEquals(
            [],
            $schema->validate()
        );
    }

    /**
     * @it accepts an Object which implements an Interface along with more fields
     */
    public function testAcceptsAnObjectWhichImplementsAnInterfaceAlongWithMoreFields()
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

       $this->assertEquals(
           [],
           $schema->validate()
       );
    }

    /**
     * @it accepts an Object which implements an Interface field along with additional optional arguments
     */
    public function testAcceptsAnObjectWhichImplementsAnInterfaceFieldAlongWithAdditionalOptionalArguments()
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

        $this->assertEquals(
            [],
            $schema->validate()
        );
    }

    /**
     * @it rejects an Object missing an Interface field
     */
    public function testRejectsAnObjectMissingAnInterfaceField()
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
                'message' => 'Interface field AnotherInterface.field expected but ' .
                             'AnotherObject does not provide it.',
                'locations' => [['line' => 7, 'column' => 9], ['line' => 10, 'column' => 7]],
            ]]
        );
    }

    /**
     * @it rejects an Object with an incorrectly typed Interface field
     */
    public function testRejectsAnObjectWithAnIncorrectlyTypedInterfaceField()
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
                'message' => 'Interface field AnotherInterface.field expects type String but ' .
                    'AnotherObject.field is type Int.',
                'locations' => [['line' => 7, 'column' => 31], ['line' => 11, 'column' => 31]],
            ]]
        );
    }

    /**
     * @it rejects an Object with a differently typed Interface field
     */
    public function testRejectsAnObjectWithADifferentlyTypedInterfaceField()
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
                'message' => 'Interface field AnotherInterface.field expects type A but ' .
                    'AnotherObject.field is type B.',
                'locations' => [['line' => 10, 'column' => 16], ['line' => 14, 'column' => 16]],
            ]]
        );
    }

    /**
     * @it accepts an Object with a subtyped Interface field (interface)
     */
    public function testAcceptsAnObjectWithASubtypedInterfaceFieldForInterface()
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

        $this->assertEquals([], $schema->validate());
    }

    /**
     * @it accepts an Object with a subtyped Interface field (union)
     */
    public function testAcceptsAnObjectWithASubtypedInterfaceFieldForUnion()
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

        $this->assertEquals([],$schema->validate());
    }

    /**
     * @it rejects an Object missing an Interface argument
     */
    public function testRejectsAnObjectMissingAnInterfaceArgument()
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
                'message' => 'Interface field argument AnotherInterface.field(input:) expected ' .
                             'but AnotherObject.field does not provide it.',
                'locations' => [['line' => 7, 'column' => 15], ['line' => 11, 'column' => 9]],
            ]]
        );
    }

    /**
     * @it rejects an Object with an incorrectly typed Interface argument
     */
    public function testRejectsAnObjectWithAnIncorrectlyTypedInterfaceArgument()
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
                'message' => 'Interface field argument AnotherInterface.field(input:) expects ' .
                             'type String but AnotherObject.field(input:) is type Int.',
                'locations' => [['line' => 7, 'column' => 22], ['line' => 11, 'column' => 22]],
            ]]
        );
    }

    /**
     * @it rejects an Object with both an incorrectly typed field and argument
     */
    public function testRejectsAnObjectWithBothAnIncorrectlyTypedFieldAndArgument()
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
            [[
                'message' => 'Interface field AnotherInterface.field expects type String but ' .
                             'AnotherObject.field is type Int.',
                'locations' => [['line' => 7, 'column' => 31], ['line' => 11, 'column' => 28]],
            ], [
                'message' => 'Interface field argument AnotherInterface.field(input:) expects ' .
                             'type String but AnotherObject.field(input:) is type Int.',
                'locations' => [['line' => 7, 'column' => 22], ['line' => 11, 'column' => 22]],
            ]]
        );
    }

    /**
     * @it rejects an Object which implements an Interface field along with additional required arguments
     */
    public function testRejectsAnObjectWhichImplementsAnInterfaceFieldAlongWithAdditionalRequiredArguments()
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
                'message' => 'Object field argument AnotherObject.field(anotherInput:) is of ' .
                             'required type String! but is not also provided by the Interface ' .
                             'field AnotherInterface.field.',
                'locations' => [['line' => 11, 'column' => 44], ['line' => 7, 'column' => 9]],
            ]]
        );
    }

    /**
     * @it accepts an Object with an equivalently wrapped Interface field type
     */
    public function testAcceptsAnObjectWithAnEquivalentlyWrappedInterfaceFieldType()
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

        $this->assertEquals([], $schema->validate());
    }

    /**
     * @it rejects an Object with a non-list Interface field list type
     */
    public function testRejectsAnObjectWithANonListInterfaceFieldListType()
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
                'message' => 'Interface field AnotherInterface.field expects type [String] ' .
                             'but AnotherObject.field is type String.',
                'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
            ]]
        );
    }

    /**
     * @it rejects an Object with a list Interface field non-list type
     */
    public function testRejectsAnObjectWithAListInterfaceFieldNonListType()
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
                'message' => 'Interface field AnotherInterface.field expects type String but ' .
                             'AnotherObject.field is type [String].',
                'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
            ]]
        );
    }

    /**
     * @it accepts an Object with a subset non-null Interface field type
     */
    public function testAcceptsAnObjectWithASubsetNonNullInterfaceFieldType()
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

        $this->assertEquals([], $schema->validate());
    }

    /**
     * @it rejects an Object with a superset nullable Interface field type
     */
    public function testRejectsAnObjectWithASupersetNullableInterfaceFieldType()
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
                'message' => 'Interface field AnotherInterface.field expects type String! ' .
                             'but AnotherObject.field is type String.',
                'locations' => [['line' => 7, 'column' => 16], ['line' => 11, 'column' => 16]],
            ]]
        );
    }

    public function testRejectsDifferentInstancesOfTheSameType()
    {
        // Invalid: always creates new instance vs returning one from registry
        $typeLoader = function($name) use (&$typeLoader) {
            switch ($name) {
                case 'Query':
                    return new ObjectType([
                        'name' => 'Query',
                        'fields' => [
                            'test' => Type::string()
                        ]
                    ]);
                default:
                    return null;
            }
        };

        $schema = new Schema([
            'query' => $typeLoader('Query'),
            'typeLoader' => $typeLoader
        ]);
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Type loader returns different instance for Query than field/argument definitions. '.
            'Make sure you always return the same instance for the same type name.'
        );
        $schema->assertValid();
    }


    private function assertEachCallableThrows($closures, $expectedError)
    {
        foreach ($closures as $index => $factory) {
            try {
                $factory();
                $this->fail('Expected exception not thrown for entry ' . $index);
            } catch (InvariantViolation $e) {
                $this->assertEquals($expectedError, $e->getMessage(), 'Error in callable #' . $index);
            }
        }
    }

    private function schemaWithFieldType($type)
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['f' => ['type' => $type]]
            ]),
            'types' => [$type],
        ]);
    }

    private function schemaWithObjectFieldOfType($fieldType)
    {
        $BadObjectType = new ObjectType([
            'name' => 'BadObject',
            'fields' => [
                'badField' => ['type' => $fieldType]
            ]
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadObjectType]
                ]
            ]),
            'types' => [$this->SomeObjectType]
        ]);
    }

    private function withModifiers($types)
    {
        return array_merge(
            $types,
            Utils::map($types, function ($type) {
                return Type::listOf($type);
            }),
            Utils::map($types, function ($type) {
                return Type::nonNull($type);
            }),
            Utils::map($types, function ($type) {
                return Type::nonNull(Type::listOf($type));
            })
        );
    }

    private function schemaWithInterfaceFieldOfType($fieldType)
    {
        $BadInterfaceType = new InterfaceType([
            'name' => 'BadInterface',
            'fields' => [
                'badField' => ['type' => $fieldType]
            ]
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadInterfaceType]
                ]
            ]),
        ]);
    }

    private function schemaWithArgOfType($argType)
    {
        $BadObjectType = new ObjectType([
            'name' => 'BadObject',
            'fields' => [
                'badField' => [
                    'type' => Type::string(),
                    'args' => [
                        'badArg' => ['type' => $argType]
                    ]
                ]
            ]
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => ['type' => $BadObjectType]
                ]
            ])
        ]);
    }

    private function schemaWithInputFieldOfType($inputFieldType)
    {
        $BadInputObjectType = new InputObjectType([
            'name' => 'BadInputObject',
            'fields' => [
                'badField' => ['type' => $inputFieldType]
            ]
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'f' => [
                        'type' => Type::string(),
                        'args' => [
                            'badArg' => ['type' => $BadInputObjectType]
                        ]
                    ]
                ]
            ])
        ]);
    }
}
