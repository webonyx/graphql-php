<?php
namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;

class ValidationTest extends \PHPUnit_Framework_TestCase
{
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
        ], 'Must be named. Unexpected name: null');
    }

    public function testRejectsAnObjectTypeWithReservedName()
    {
        $this->assertEachCallableThrows([
            function() {
                return new ObjectType([
                    'name' => '__ReservedName',
                ]);
            },
            function() {
                return new EnumType([
                    'name' => '__ReservedName',
                ]);
            },
            function() {
                return new InputObjectType([
                    'name' => '__ReservedName',
                ]);
            },
            function() {
                return new UnionType([
                    'name' => '__ReservedName',
                ]);
            },
            function() {
                return new InterfaceType([
                    'name' => '__ReservedName',
                ]);
            }
        ], 'Name "__ReservedName" must not begin with "__", which is reserved by GraphQL introspection.');
    }

    public function testRejectsAnObjectTypeWithInvalidName()
    {
        $this->assertEachCallableThrows([
            function() {
                return new ObjectType([
                    'name' => 'a-b-c',
                ]);
            },
            function() {
                return new EnumType([
                    'name' => 'a-b-c',
                ]);
            },
            function() {
                return new InputObjectType([
                    'name' => 'a-b-c',
                ]);
            },
            function() {
                return new UnionType([
                    'name' => 'a-b-c',
                ]);
            },
            function() {
                return new InterfaceType([
                    'name' => 'a-b-c',
                ]);
            }
        ], 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "a-b-c" does not.');
    }

    // DESCRIBE: Type System: A Schema must have Object root types
    // TODO: accepts a Schema whose query type is an object type
    // TODO: accepts a Schema whose query and mutation types are object types
    // TODO: accepts a Schema whose query and subscription types are object types
    // TODO: rejects a Schema without a query type
    // TODO: rejects a Schema whose query type is an input type
    // TODO: rejects a Schema whose mutation type is an input type
    // TODO: rejects a Schema whose subscription type is an input type
    // TODO: rejects a Schema whose directives are incorrectly typed

    // DESCRIBE: Type System: A Schema must contain uniquely named types
    // TODO: rejects a Schema which redefines a built-in type
    // TODO: rejects a Schema which defines an object type twice
    // TODO: rejects a Schema which have same named objects implementing an interface

    // DESCRIBE: Type System: Objects must have fields

    // TODO: accepts an Object type with fields object
    // TODO: accepts an Object type with a field function

    // TODO: rejects an Object type with missing fields
    // TODO: rejects an Object type with incorrectly named fields
    // TODO: rejects an Object type with reserved named fields
    // TODO: rejects an Object type with incorrectly typed fields
    // TODO: rejects an Object type with empty fields
    // TODO: rejects an Object type with a field function that returns nothing
    // TODO: rejects an Object type with a field function that returns empty

    // DESCRIBE: Type System: Fields args must be properly named
    // TODO: accepts field args with valid names
    // TODO: rejects field arg with invalid names

    // DESCRIBE: Type System: Fields args must be objects
    // TODO: accepts an Object type with field args
    // TODO: rejects an Object type with incorrectly typed field args

    // DESCRIBE: Type System: Object interfaces must be array
    // TODO: accepts an Object type with array interfaces
    // TODO: accepts an Object type with interfaces as a function returning an array
    // TODO: rejects an Object type with incorrectly typed interfaces
    // TODO: rejects an Object type with interfaces as a function returning an incorrect type

    // DESCRIBE: Type System: Union types must be array
    // TODO: accepts a Union type with array types
    // TODO: accepts a Union type with function returning an array of types
    // TODO: rejects a Union type without types
    // TODO: rejects a Union type with empty types
    // TODO: rejects a Union type with incorrectly typed types

    // DESCRIBE: Type System: Input Objects must have fields
    // TODO: accepts an Input Object type with fields
    // TODO: accepts an Input Object type with a field function
    // TODO: rejects an Input Object type with missing fields
    // TODO: rejects an Input Object type with incorrectly typed fields
    // TODO: rejects an Input Object type with empty fields
    // TODO: rejects an Input Object type with a field function that returns nothing
    // TODO: rejects an Input Object type with a field function that returns empty

    // DESCRIBE: Type System: Object types must be assertable
    // TODO: accepts an Object type with an isTypeOf function
    // TODO: rejects an Object type with an incorrect type for isTypeOf

    // DESCRIBE: Type System: Interface types must be resolvable
    // TODO: accepts an Interface type defining resolveType
    // TODO: accepts an Interface with implementing type defining isTypeOf
    // TODO: accepts an Interface type defining resolveType with implementing type defining isTypeOf
    // TODO: rejects an Interface type with an incorrect type for resolveType
    // TODO: rejects an Interface type not defining resolveType with implementing type not defining isTypeOf

    // DESCRIBE: Type System: Union types must be resolvable
    // TODO: accepts a Union type defining resolveType
    // TODO: accepts a Union of Object types defining isTypeOf
    // TODO: accepts a Union type defining resolveType of Object types defining isTypeOf
    // TODO: rejects an Interface type with an incorrect type for resolveType
    // TODO: rejects a Union type not defining resolveType of Object types not defining isTypeOf

    // DESCRIBE: Type System: Scalar types must be serializable
    // TODO: accepts a Scalar type defining serialize
    // TODO: rejects a Scalar type not defining serialize
    // TODO: rejects a Scalar type defining serialize with an incorrect type
    // TODO: accepts a Scalar type defining parseValue and parseLiteral
    // TODO: rejects a Scalar type defining parseValue but not parseLiteral
    // TODO: rejects a Scalar type defining parseLiteral but not parseValue
    // TODO: rejects a Scalar type defining parseValue and parseLiteral with an incorrect type

    // DESCRIBE: Type System: Enum types must be well defined
    // TODO: accepts a well defined Enum type with empty value definition
    // TODO: accepts a well defined Enum type with internal value definition
    // TODO: rejects an Enum type without values
    // TODO: rejects an Enum type with empty values
    // TODO: rejects an Enum type with incorrectly typed values
    // TODO: rejects an Enum type with missing value definition
    // TODO: rejects an Enum type with incorrectly typed value definition

    // DESCRIBE: Type System: Object fields must have output types
    // TODO: accepts an output type as an Object field type
    // TODO: rejects an empty Object field type
    // TODO: rejects a non-output type as an Object field type

    // DESCRIBE: Type System: Objects can only implement interfaces
    // TODO: accepts an Object implementing an Interface

    // DESCRIBE: Type System: Unions must represent Object types
    // TODO: accepts a Union of an Object Type
    // TODO: rejects a Union of a non-Object type

    // DESCRIBE: Type System: Interface fields must have output types
    // TODO: accepts an output type as an Interface field type
    // TODO: rejects an empty Interface field type
    // TODO: rejects a non-output type as an Interface field type

    // DESCRIBE: Type System: Field arguments must have input types
    // TODO: accepts an input type as a field arg type
    // TODO: rejects an empty field arg type
    // TODO: rejects a non-input type as a field arg type

    // DESCRIBE: Type System: Input Object fields must have input types
    // TODO: accepts an input type as an input field type
    // TODO: rejects an empty input field type
    // TODO: rejects a non-input type as an input field type

    // DESCRIBE: Type System: List must accept GraphQL types
    // TODO: accepts an type as item type of list: ${type}
    // TODO: rejects a non-type as item type of list: ${type}

    // DESCRIBE: Type System: NonNull must accept GraphQL types
    // TODO: accepts an type as nullable type of non-null: ${type}
    // TODO: rejects a non-type as nullable type of non-null: ${type}

    // DESCRIBE: Objects must adhere to Interface they implement
    // TODO: accepts an Object which implements an Interface
    // TODO: accepts an Object which implements an Interface along with more fields
    // TODO: accepts an Object which implements an Interface field along with additional optional arguments
    // TODO: rejects an Object which implements an Interface field along with additional required arguments
    // TODO: rejects an Object missing an Interface field
    // TODO: rejects an Object with an incorrectly typed Interface field
    // TODO: rejects an Object with a differently typed Interface field
    // TODO: accepts an Object with a subtyped Interface field (interface)
    // TODO: accepts an Object with a subtyped Interface field (union)
    // TODO: rejects an Object missing an Interface argument
    // TODO: rejects an Object with an incorrectly typed Interface argument
    // TODO: accepts an Object with an equivalently modified Interface field type
    // TODO: rejects an Object with a non-list Interface field list type
    // TODO: rejects an Object with a list Interface field non-list type
    // TODO: accepts an Object with a subset non-null Interface field type
    // TODO: rejects an Object with a superset nullable Interface field type
    // TODO: does not allow isDeprecated without deprecationReason on field
    // TODO: does not allow isDeprecated without deprecationReason on enum

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
                'fields' => [ 'f' => $type ]
            ]),
            'types' => [ $type ]
        ]);
    }
}
