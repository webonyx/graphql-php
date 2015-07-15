<?php
namespace GraphQL\Type;

use GraphQL\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;

class SchemaValidatorTest extends \PHPUnit_Framework_TestCase
{
    public $someInputType;

    public function setUp()
    {
        $this->someInputType = new InputObjectType([
            'name' => 'SomeInputType',
            'fields' => [
                'val' => [ 'type' => Type::float(), 'defaultValue' => 42 ]
            ]
        ]);
    }


    // Type System Config
    public function testPassesOnTheIntrospectionSchema()
    {
        $schema = new Schema(Introspection::_schema());
        $validationResult = SchemaValidator::validate($schema);

        $this->assertSame(true, $validationResult->isValid);
        $this->assertSame(null, $validationResult->errors);
    }


    // Rule: NoInputTypesAsOutputFields
    public function testRejectsSchemaWhoseQueryOrMutationTypeIsAnInputType()
    {
        $schema = new Schema($this->someInputType);
        $validationResult = SchemaValidator::validate($schema, [SchemaValidator::noInputTypesAsOutputFieldsRule()]);
        $this->checkValidationResult($validationResult, 'query');

        $schema = new Schema(null, $this->someInputType);
        $validationResult = SchemaValidator::validate($schema, [SchemaValidator::noInputTypesAsOutputFieldsRule()]);
        $this->checkValidationResult($validationResult, 'mutation');
    }

    public function testRejectsASchemaThatUsesAnInputTypeAsAField()
    {
        $kinds = [
            'GraphQL\Type\Definition\ObjectType',
            'GraphQL\Type\Definition\InterfaceType',
        ];
        foreach ($kinds as $kind) {
            $someOutputType = new $kind([
                'name' => 'SomeOutputType',
                'fields' => [
                    'sneaky' => ['type' => function() {return $this->someInputType;}]
                ]
            ]);

            $schema = new Schema($someOutputType);
            $validationResult = SchemaValidator::validate($schema, [SchemaValidator::noInputTypesAsOutputFieldsRule()]);

            $this->assertSame(false, $validationResult->isValid);
            $this->assertSame(1, count($validationResult->errors));
            $this->assertSame(
                'Field SomeOutputType.sneaky is of type SomeInputType, which is an ' .
                'input type, but field types must be output types!',
                $validationResult->errors[0]->message
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
                    'args' => ['someArg' => ['type' => $this->someInputType]],
                    'type' => Type::float()
                ]
            ]
        ]);

        $schema = new Schema($someOutputType);
        $validationResult = SchemaValidator::validate($schema, [$rule]);
        $this->assertSame(true, $validationResult->isValid);
    }

    private function checkValidationResult($validationResult, $operationType)
    {
        $this->assertEquals(false, $validationResult->isValid);
        $this->assertEquals(1, count($validationResult->errors));
        $this->assertEquals(
            "Schema $operationType type SomeInputType must be an object type!",
            $validationResult->errors[0]->message
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
            'name' => 'SomeInputType',
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
            'name' => 'SomeInputType'
        ])));
    }

    public function testAcceptsSchemaWithNonnullInputTypeAsInputFieldArg()
    {
        // accepts a schema with a nonnull input type as an input field arg
        $this->assertAcceptingFieldArgOfType(new NonNull(new InputObjectType([
            'name' => 'SomeInputType'
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
        $validationResult = SchemaValidator::validate($schema, [SchemaValidator::noOutputTypesAsInputArgsRule()]);
        $this->assertSame(true, $validationResult->isValid);
    }

    private function schemaWithFieldArgOfType($argType)
    {
        $someIncorrectInputType = new InputObjectType([
            'name' => 'SomeIncorrectInputType',
            'fields' => [
                'val' => ['type' => function() use ($argType) {return $argType;} ]
            ]
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

        return new Schema($queryType);
    }

    private function expectRejectionBecauseFieldIsNotInputType($validationResult, $fieldTypeName)
    {
        $this->assertSame(false, $validationResult->isValid);
        $this->assertSame(1, count($validationResult->errors));
        $this->assertSame(
            "Input field SomeIncorrectInputType.val has type $fieldTypeName, " .
            "which is not an input type!",
            $validationResult->errors[0]->message
        );
    }


    // Rule: InterfacePossibleTypesMustImplementTheInterface

    public function testAcceptsInterfaceWithSubtypeDeclaredUsingOurInfra()
    {
        // accepts an interface with a subtype declared using our infra
        $this->assertAcceptingAnInterfaceWithANormalSubtype(SchemaValidator::interfacePossibleTypesMustImplementTheInterfaceRule());
    }

    public function testRejectsWhenAPossibleTypeDoesNotImplementTheInterface()
    {
        // rejects when a possible type does not implement the interface
        $InterfaceType = new InterfaceType([
            'name' => 'InterfaceType',
            'fields' => []
        ]);

        $SubType = new ObjectType([
            'name' => 'SubType',
            'fields' => [],
            'interfaces' => []
        ]);

        InterfaceType::addImplementationToInterfaces($SubType, [$InterfaceType]);

        // Sanity check.
        $this->assertEquals(1, count($InterfaceType->getPossibleTypes()));
        $this->assertEquals($SubType, $InterfaceType->getPossibleTypes()[0]);


        $schema = new Schema($InterfaceType);
        $validationResult = SchemaValidator::validate(
            $schema,
            [SchemaValidator::interfacePossibleTypesMustImplementTheInterfaceRule()]
        );
        $this->assertSame(false, $validationResult->isValid);
        $this->assertSame(1, count($validationResult->errors));
        $this->assertSame(
            'SubType is a possible type of interface InterfaceType but does not ' .
            'implement it!',
            $validationResult->errors[0]->message
        );
    }


    private function assertAcceptingAnInterfaceWithANormalSubtype($rule)
    {
        $interfaceType = new InterfaceType([
            'name' => 'InterfaceType',
            'fields' => []
        ]);

        $subType = new ObjectType([
            'name' => 'SubType',
            'fields' => [],
            'interfaces' => [$interfaceType]
        ]);

        $schema = new Schema($interfaceType, $subType);

        $validationResult = SchemaValidator::validate($schema, [$rule]);
        $this->assertSame(true, $validationResult->isValid);
    }


    // Rule: TypesInterfacesMustShowThemAsPossible

    public function testAcceptsInterfaceWithASubtypeDeclaredUsingOurInfra()
    {
        // accepts an interface with a subtype declared using our infra
        $this->assertAcceptingAnInterfaceWithANormalSubtype(SchemaValidator::typesInterfacesMustShowThemAsPossibleRule());
    }

    public function testRejectsWhenAnImplementationIsNotAPossibleType()
    {
        // rejects when an implementation is not a possible type
        $interfaceType = new InterfaceType([
            'name' => 'InterfaceType',
            'fields' => []
        ]);

        $subType = new ObjectType([
            'name' => 'SubType',
            'fields' => [],
            'interfaces' => []
        ]);

        $tmp = new \ReflectionObject($subType);
        $prop = $tmp->getProperty('_interfaces');
        $prop->setAccessible(true);
        $prop->setValue($subType, [$interfaceType]);

        // Sanity check the test.
        $this->assertEquals([$interfaceType], $subType->getInterfaces());
        $this->assertSame(false, $interfaceType->isPossibleType($subType));

        // Need to make sure SubType is in the schema! We rely on
        // possibleTypes to be able to see it unless it's explicitly used.
        $schema = new Schema($interfaceType, $subType);

        // Another sanity check.
        $this->assertSame($subType, $schema->getType('SubType'));

        $validationResult = SchemaValidator::validate($schema, [SchemaValidator::typesInterfacesMustShowThemAsPossibleRule()]);
        $this->assertSame(false, $validationResult->isValid);
        $this->assertSame(1, count($validationResult->errors));
        $this->assertSame(
            'SubType implements interface InterfaceType, but InterfaceType does ' .
            'not list it as possible!',
            $validationResult->errors[0]->message
        );


/*

    var validationResult = validateSchema(
      schema,
      [TypesInterfacesMustShowThemAsPossible]
    );
    expect(validationResult.isValid).to.equal(false);
    expect(validationResult.errors.length).to.equal(1);
    expect(validationResult.errors[0].message).to.equal(
      'SubType implements interface InterfaceType, but InterfaceType does ' +
      'not list it as possible!'
    );
 */
    }
}
