<?php
namespace GraphQL\Tests\Type;

use GraphQL\Schema;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Type\SchemaValidator;
use GraphQL\Utils;

class SchemaValidatorTest extends \PHPUnit_Framework_TestCase
{
    private $someInputObjectType;

    private $someScalarType;

    private $someObjectType;

    private $objectWithIsTypeOf;

    private $someEnumType;

    private $someUnionType;

    private $someInterfaceType;

    private $outputTypes;

    private $noOutputTypes;

    private $inputTypes;

    private $noInputTypes;

    public function setUp()
    {
        $this->someScalarType = new CustomScalarType([
            'name' => 'SomeScalar',
            'serialize' => function() {},
            'parseValue' => function() {},
            'parseLiteral' => function() {}
        ]);

        $this->someObjectType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => ['f' => ['type' => Type::string()]]
        ]);

        $this->objectWithIsTypeOf = new ObjectType([
            'name' => 'ObjectWithIsTypeOf',
            'isTypeOf' => function() {return true;},
            'fields' => ['f' => ['type' => Type::string()]]
        ]);

        $this->someUnionType = new UnionType([
            'name' => 'SomeUnion',
            'resolveType' => function() {return null;},
            'types' => [$this->someObjectType]
        ]);

        $this->someInterfaceType = new InterfaceType([
            'name' => 'SomeInterface',
            'resolveType' => function() {return null;},
            'fields' => ['f' => ['type' => Type::string()]]
        ]);

        $this->someEnumType = new EnumType([
            'name' => 'SomeEnum',
            'resolveType' => function() {return null;},
            'fields' => ['f' => ['type' => Type::string()]]
        ]);

        $this->someInputObjectType = new InputObjectType([
            'name' => 'SomeInputObject',
            'fields' => [
                'val' => ['type' => Type::float(), 'defaultValue' => 42]
            ]
        ]);

        $this->outputTypes = $this->withModifiers([
            Type::string(),
            $this->someScalarType,
            $this->someEnumType,
            $this->someObjectType,
            $this->someUnionType,
            $this->someInterfaceType
        ]);

        $this->noOutputTypes = $this->withModifiers([
            $this->someInputObjectType
        ]);
        $this->noOutputTypes[] = 'SomeString';

        $this->inputTypes = $this->withModifiers([
            Type::string(),
            $this->someScalarType,
            $this->someEnumType,
            $this->someInputObjectType
        ]);

        $this->noInputTypes = $this->withModifiers([
            $this->someObjectType,
            $this->someUnionType,
            $this->someInterfaceType
        ]);
        $this->noInputTypes[] = 'SomeString';
    }

    private function withModifiers($types)
    {
        return array_merge(
            Utils::map($types, function($type) {return Type::listOf($type);}),
            Utils::map($types, function($type) {return Type::nonNull($type);}),
            Utils::map($types, function($type) {return Type::nonNull(Type::listOf($type));})
        );
    }

    private function schemaWithFieldType($type)
    {
        return [
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['f' => ['type' => $type]]
            ]),
            'types' => [$type],
        ];
    }

    private function expectPasses($schemaConfig)
    {
        $schema = new Schema(['validate' => true] + $schemaConfig);
        $errors = SchemaValidator::validate($schema);
        $this->assertEquals([], $errors);
    }

    private function expectFails($schemaConfig, $error)
    {
        try {
            $schema = new Schema($schemaConfig);
            $errors = SchemaValidator::validate($schema);
            if ($errors) {
                throw $errors[0];
            }
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals($e->getMessage(), $error);
        }
    }

    // Type System: A Schema must have Object root types

    /**
     * @it accepts a Schema whose query type is an object type
     */
    public function testAcceptsSchemaWithQueryTypeOfObjectType()
    {
        $this->expectPasses([
            'query' => $this->someObjectType
        ]);
    }

    /**
     * @it accepts a Schema whose query and mutation types are object types
     */
    public function testAcceptsSchemaWithQueryAndMutationTypesOfObjectType()
    {
        $MutationType = new ObjectType([
            'name' => 'Mutation',
            'fields' => ['edit' => ['type' => Type::string()]]
        ]);

        $this->expectPasses([
            'query' => $this->someObjectType,
            'mutation' => $MutationType
        ]);
    }

    /**
     * @it accepts a Schema whose query and subscription types are object types
     */
    public function testAcceptsSchemaWhoseQueryAndSubscriptionTypesAreObjectTypes()
    {
        $SubscriptionType = new ObjectType([
            'name' => 'Subscription',
            'fields' => ['subscribe' => ['type' => Type::string()]]
        ]);

        $this->expectPasses([
            'query' => $this->someObjectType,
            'subscription' => $SubscriptionType
        ]);
    }

    /**
     * @it rejects a Schema without a query type
     */
    public function testRejectsSchemaWithoutQueryType()
    {
        $this->expectFails([], 'Schema query must be Object Type but got: NULL');
    }

    /**
     * @it rejects a Schema whose query type is an input type
     */
    public function testRejectsSchemaWhoseQueryTypeIsAnInputType()
    {
        $this->expectFails(
            ['query' => $this->someInputObjectType],
            'Schema query must be Object Type but got: SomeInputObject'
        );
    }

    /**
     * @it rejects a Schema whose mutation type is an input type
     */
    public function testRejectsSchemaWhoseMutationTypeIsInputType()
    {
        $this->expectFails(
            ['query' => $this->someObjectType, 'mutation' => $this->someInputObjectType],
            'Schema mutation must be Object Type if provided but got: SomeInputObject'
        );
    }

    /**
     * @it rejects a Schema whose subscription type is an input type
     */
    public function testRejectsSchemaWhoseSubscriptionTypeIsInputType()
    {
        $this->expectFails(
            [
                'query' => $this->someObjectType,
                'subscription' => $this->someInputObjectType
            ],
            'Schema subscription must be Object Type if provided but got: SomeInputObject'
        );
    }

    /**
     * @it rejects a Schema whose directives are incorrectly typed
     */
    public function testRejectsSchemaWhoseDirectivesAreIncorrectlyTyped()
    {
        $this->expectFails(
            [
                'query' => $this->someObjectType,
                'directives' => [ 'somedirective' ]
            ],
            'Schema directives must be Directive[] if provided but got array'
        );
    }

    // Type System: A Schema must contain uniquely named types

    /**
     * @it rejects a Schema which redefines a built-in type
     */
    public function testRejectsSchemaWhichRedefinesBuiltInType()
    {
        $FakeString = new CustomScalarType([
            'name' => 'String',
            'serialize' => function() {return null;},
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'normal' => [ 'type' => Type::string() ],
                'fake' => [ 'type' => $FakeString ],
            ]
        ]);

        $this->expectFails(
            [ 'query' => $QueryType ],
            'Schema must contain unique named types but contains multiple types named "String".'
        );
    }

    /**
     * @it rejects a Schema which defines an object type twice
     */
    public function testRejectsSchemaWhichDefinesObjectTypeTwice()
    {
        $A = new ObjectType([
            'name' => 'SameName',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $B = new ObjectType([
            'name' => 'SameName',
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'a' => ['type' => $A],
                'b' => ['type' => $B]
            ]
        ]);

        $this->expectFails(
            ['query' => $QueryType],
            'Schema must contain unique named types but contains multiple types named "SameName".'
        );
    }

    /**
     * @it rejects a Schema which have same named objects implementing an interface
     */
    public function testRejectsSchemaWhichHaveSameNamedObjectsImplementingInterface()
    {
        $AnotherInterface = new InterfaceType([
            'name' => 'AnotherInterface',
            'resolveType' => function () {
                return null;
            },
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $FirstBadObject = new ObjectType([
            'name' => 'BadObject',
            'interfaces' => [$AnotherInterface],
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $SecondBadObject = new ObjectType([
            'name' => 'BadObject',
            'interfaces' => [$AnotherInterface],
            'fields' => ['f' => ['type' => Type::string()]],
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'iface' => ['type' => $AnotherInterface],
            ]
        ]);

        $this->expectFails(
            [
                'query' => $QueryType,
                'types' => [$FirstBadObject, $SecondBadObject]
            ],
            'Schema must contain unique named types but contains multiple types named "BadObject".'
        );
    }

    // Type System: Objects must have fields

    /**
     * @it accepts an Object type with fields object
     */
    public function testAcceptsAnObjectTypeWithFieldsObject()
    {
        $schemaConfig = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'f' => [ 'type' => Type::string() ]
            ]
        ]));
        $this->expectPasses($schemaConfig);
    }

    /**
     * @it accepts an Object type with a field function
     */
    public function testAcceptsAnObjectTypeWithFieldFunction()
    {
        $schemaConfig = $this->schemaWithFieldType(new ObjectType([
            'name' => 'SomeObject',
            'fields' => function() {
                return [
                    'f' => ['type' => Type::string()]
                ];
            }
        ]));
        $this->expectPasses($schemaConfig);
    }

    // Type System Config
    public function testPassesOnTheIntrospectionSchema()
    {
        $this->expectPasses(['query' => Introspection::_schema()]);
    }

    public function testRejectsASchemaThatUsesAnInputTypeAsAField()
    {
        $kinds = [
            'GraphQL\Type\Definition\ObjectType',
        ];
        foreach ($kinds as $kind) {
            $someOutputType = new $kind([
                'name' => 'SomeOutputType',
                'fields' => function() {
                    return [
                        'sneaky' => $this->someInputObjectType
                    ];
                }
            ]);

            $schema = new Schema(['query' => $someOutputType]);
            $validationResult = SchemaValidator::validate($schema, [SchemaValidator::noInputTypesAsOutputFieldsRule()]);

            $this->assertSame(1, count($validationResult));
            $this->assertSame(
                'Field SomeOutputType.sneaky is of type SomeInputObject, which is an ' .
                'input type, but field types must be output types!',
                $validationResult[0]->message
            );
        }
    }

    public function testAcceptsASchemaThatSimplyHasAnInputTypeAsAFieldArg()
    {
        $this->expectToAcceptSchemaWithNormalInputArg(SchemaValidator::noInputTypesAsOutputFieldsRule());
    }

    private function expectToAcceptSchemaWithNormalInputArg($rule)
    {
        $someOutputType = new ObjectType([
            'name' => 'SomeOutputType',
            'fields' => [
                'fieldWithArg' => [
                    'args' => ['someArg' => ['type' => $this->someInputObjectType]],
                    'type' => Type::float()
                ]
            ]
        ]);

        $schema = new Schema(['query' => $someOutputType]);
        $errors = SchemaValidator::validate($schema, [$rule]);
        $this->assertEmpty($errors);
    }

    private function checkValidationResult($validationErrors, $operationType)
    {
        $this->assertNotEmpty($validationErrors, "Should not validate");
        $this->assertEquals(1, count($validationErrors));
        $this->assertEquals(
            "Schema $operationType must be Object Type but got: SomeInputObject.",
            $validationErrors[0]->message
        );
    }


    // Rule: NoOutputTypesAsInputArgs
    public function testAcceptsASchemaThatSimplyHasAnInputTypeAsAFieldArg2()
    {
        $this->expectToAcceptSchemaWithNormalInputArg(SchemaValidator::noOutputTypesAsInputArgsRule());
    }

    public function testRejectsASchemaWithAnObjectTypeAsAnInputFieldArg()
    {
        // rejects a schema with an object type as an input field arg
        $someOutputType = new ObjectType([
            'name' => 'SomeOutputType',
            'fields' => ['f' => ['type' => Type::float()]]
        ]);
        $this->assertRejectingFieldArgOfType($someOutputType);
    }

    public function testRejectsASchemaWithAUnionTypeAsAnInputFieldArg()
    {
        // rejects a schema with a union type as an input field arg
        $unionType = new UnionType([
            'name' => 'UnionType',
            'types' => [
                new ObjectType([
                    'name' => 'SomeOutputType',
                    'fields' => [ 'f' => [ 'type' => Type::float() ] ]
                ])
            ]
        ]);
        $this->assertRejectingFieldArgOfType($unionType);
    }

    public function testRejectsASchemaWithAnInterfaceTypeAsAnInputFieldArg()
    {
        // rejects a schema with an interface type as an input field arg
        $interfaceType = new InterfaceType([
            'name' => 'InterfaceType',
            'fields' => []
        ]);

        $this->assertRejectingFieldArgOfType($interfaceType);
    }

    public function testRejectsASchemaWithAListOfObjectsAsAnInputFieldArg()
    {
        // rejects a schema with a list of objects as an input field arg
        $listObjects = new ListOfType(new ObjectType([
            'name' => 'SomeInputObject',
            'fields' => ['f' => ['type' => Type::float()]]
        ]));
        $this->assertRejectingFieldArgOfType($listObjects);
    }

    public function testRejectsASchemaWithANonnullObjectAsAnInputFieldArg()
    {
        // rejects a schema with a nonnull object as an input field arg
        $nonNullObject = new NonNull(new ObjectType([
            'name' => 'SomeOutputType',
            'fields' => [ 'f' => [ 'type' => Type::float() ] ]
        ]));

        $this->assertRejectingFieldArgOfType($nonNullObject);
    }

    public function testAcceptsSchemaWithListOfInputTypeAsInputFieldArg()
    {
        // accepts a schema with a list of input type as an input field arg
        $this->assertAcceptingFieldArgOfType(new ListOfType(new InputObjectType([
            'name' => 'SomeInputObject'
        ])));
    }

    public function testAcceptsSchemaWithNonnullInputTypeAsInputFieldArg()
    {
        // accepts a schema with a nonnull input type as an input field arg
        $this->assertAcceptingFieldArgOfType(new NonNull(new InputObjectType([
            'name' => 'SomeInputObject'
        ])));
    }

    private function assertRejectingFieldArgOfType($fieldArgType)
    {
        $schema = $this->schemaWithFieldArgOfType($fieldArgType);
        $validationResult = SchemaValidator::validate($schema, [SchemaValidator::noOutputTypesAsInputArgsRule()]);
        $this->expectRejectionBecauseFieldIsNotInputType($validationResult, $fieldArgType);
    }

    private function assertAcceptingFieldArgOfType($fieldArgType)
    {
        $schema = $this->schemaWithFieldArgOfType($fieldArgType);
        $errors = SchemaValidator::validate($schema, [SchemaValidator::noOutputTypesAsInputArgsRule()]);
        $this->assertEmpty($errors);
    }

    private function schemaWithFieldArgOfType($argType)
    {
        $someIncorrectInputType = new InputObjectType([
            'name' => 'SomeIncorrectInputType',
            'fields' => function() use ($argType) {
                return [
                    'val' => ['type' => $argType ]
                ];
            }
        ]);

        $queryType = new ObjectType([
            'name' => 'QueryType',
            'fields' => [
                'f2' => [
                    'type' => Type::float(),
                    'args' => ['arg' => [ 'type' => $someIncorrectInputType] ]
                ]
            ]
        ]);

        return new Schema(['query' => $queryType]);
    }

    private function expectRejectionBecauseFieldIsNotInputType($errors, $fieldTypeName)
    {
        $this->assertSame(1, count($errors));
        $this->assertSame(
            "Input field SomeIncorrectInputType.val has type $fieldTypeName, " .
            "which is not an input type!",
            $errors[0]->message
        );
    }


    public function testRejectsWhenAPossibleTypeDoesNotImplementTheInterface()
    {
        // TODO: Validation for interfaces / implementors
    }
}
