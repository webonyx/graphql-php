<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaExtender;

/**
 * Tests that were moved from the reference implementation.
 * TODO add the proper tests wherever they were moved.
 */
class OldValidationTest extends ValidationTest
{
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
                static fn (): ScalarType => new CustomScalarType([]),
            ],
            'Must provide name.'
        );
    }

    /**
     * @param array<int, Closure(): Type> $closures
     */
    private function assertEachCallableThrows(array $closures, string $expectedError): void
    {
        foreach ($closures as $index => $factory) {
            try {
                $factory();
                self::fail("Expected exception not thrown for entry {$index}");
            } catch (InvariantViolation $e) {
                self::assertEquals($expectedError, $e->getMessage(), "Error in callable #{$index}");
            }
        }
    }

    /**
     * @see describe('Type System: Schema directives must validate')
     * @see it('accepts a Schema with valid directives')
     */
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

        self::assertEquals([], $schema->validate());
    }

    /**
     * @see it('rejects a Schema with directive defined multiple times')
     */
    public function testRejectsASchemaWithDirectiveDefinedMultipleTimes(): void
    {
        $schema = BuildSchema::build('
          type Query {
            test: String
          }
    
          directive @testA on SCHEMA
          directive @testA on SCHEMA
        ');
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

    /**
     * @see it('rejects a Schema with same directive used twice per location')
     */
    public function testRejectsASchemaWithSameSchemaDirectiveUsedTwice(): void
    {
        $schema = BuildSchema::build('
          directive @schema on SCHEMA
          directive @object on OBJECT
          directive @interface on INTERFACE
          directive @union on UNION
          directive @scalar on SCALAR
          directive @input_object on INPUT_OBJECT
          directive @enum on ENUM
          directive @field_definition on FIELD_DEFINITION
          directive @enum_value on ENUM_VALUE
          directive @input_field_definition on INPUT_FIELD_DEFINITION
          directive @argument_definition on ARGUMENT_DEFINITION

          schema @schema @schema {
            query: Query
          }

          type Query implements SomeInterface @object @object {
            test(arg: SomeInput @argument_definition @argument_definition): String
          }

          interface SomeInterface @interface @interface {
            test: String @field_definition @field_definition
          }

          union SomeUnion @union @union = Query

          scalar SomeScalar @scalar @scalar

          enum SomeEnum @enum @enum {
            SOME_VALUE @enum_value @enum_value
          }

          input SomeInput @input_object @input_object {
            some_input_field: String @input_field_definition @input_field_definition
          }
        ', null, ['assumeValid' => true]);
        $this->assertMatchesValidationMessage(
            $schema->validate(),
            [
                [
                    'message' => 'Non-repeatable directive @schema used more than once at the same location.',
                    'locations' => [['line' => 14, 'column' => 18], ['line' => 14, 'column' => 26]],
                ],
                [
                    'message' => 'Non-repeatable directive @argument_definition used more than once at the same location.',
                    'locations' => [['line' => 19, 'column' => 33], ['line' => 19, 'column' => 54]],
                ],
                [
                    'message' => 'Non-repeatable directive @object used more than once at the same location.',
                    'locations' => [['line' => 18, 'column' => 47], ['line' => 18, 'column' => 55]],
                ],
                [
                    'message' => 'Non-repeatable directive @field_definition used more than once at the same location.',
                    'locations' => [['line' => 23, 'column' => 26], ['line' => 23, 'column' => 44]],
                ],
                [
                    'message' => 'Non-repeatable directive @interface used more than once at the same location.',
                    'locations' => [['line' => 22, 'column' => 35], ['line' => 22, 'column' => 46]],
                ],
                [
                    'message' => 'Non-repeatable directive @input_field_definition used more than once at the same location.',
                    'locations' => [['line' => 35, 'column' => 38], ['line' => 35, 'column' => 62]],
                ],
                [
                    'message' => 'Non-repeatable directive @input_object used more than once at the same location.',
                    'locations' => [['line' => 34, 'column' => 27], ['line' => 34, 'column' => 41]],
                ],
                [
                    'message' => 'Non-repeatable directive @union used more than once at the same location.',
                    'locations' => [['line' => 26, 'column' => 27], ['line' => 26, 'column' => 34]],
                ],
                [
                    'message' => 'Non-repeatable directive @scalar used more than once at the same location.',
                    'locations' => [['line' => 28, 'column' => 29], ['line' => 28, 'column' => 37]],
                ],
                [
                    'message' => 'Non-repeatable directive @enum_value used more than once at the same location.',
                    'locations' => [['line' => 31, 'column' => 24], ['line' => 31, 'column' => 36]],
                ],
                [
                    'message' => 'Non-repeatable directive @enum used more than once at the same location.',
                    'locations' => [['line' => 30, 'column' => 25], ['line' => 30, 'column' => 31]],
                ],
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

    /**
     * @see it('rejects a Schema with directive used again in extension')
     */
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

    /**
     * @see it('rejects a Schema with directives used in wrong location')
     */
    public function testRejectsASchemaWithDirectivesUsedInWrongLocation(): void
    {
        $schema = BuildSchema::build('
          directive @schema on SCHEMA
          directive @object on OBJECT
          directive @interface on INTERFACE
          directive @union on UNION
          directive @scalar on SCALAR
          directive @input_object on INPUT_OBJECT
          directive @enum on ENUM
          directive @field_definition on FIELD_DEFINITION
          directive @enum_value on ENUM_VALUE
          directive @input_field_definition on INPUT_FIELD_DEFINITION
          directive @argument_definition on ARGUMENT_DEFINITION
    
          schema @object {
            query: Query
          }
    
          type Query implements SomeInterface @schema {
            test(arg: SomeInput @field_definition): String
          }
    
          interface SomeInterface @interface {
            test: String @argument_definition
          }
    
          union SomeUnion @interface = Query
    
          scalar SomeScalar @enum_value
    
          enum SomeEnum @input_object {
            SOME_VALUE @enum
          }
    
          input SomeInput @object {
            some_input_field: String @union @input_field_definition
          }
        ', null, ['assumeValid' => true]);

        $extensions = Parser::parse('
          extend type Query @testA
        ');

        $extendedSchema = SchemaExtender::extend(
            $schema,
            $extensions,
            ['assumeValid' => true] // TODO: remove this line
        );

        $this->assertMatchesValidationMessage(
            $extendedSchema->validate(),
            [
                [
                    'message' => 'Directive @object not allowed at SCHEMA location.',
                    'locations' => [['line' => 14, 'column' => 18], ['line' => 3, 'column' => 11]],
                ],
                [
                    'message' => 'Directive @field_definition not allowed at ARGUMENT_DEFINITION location.',
                    'locations' => [['line' => 19, 'column' => 33], ['line' => 9, 'column' => 11]],
                ],
                [
                    'message' => 'Directive @schema not allowed at OBJECT location.',
                    'locations' => [['line' => 18, 'column' => 47], ['line' => 2, 'column' => 11]],
                ],
                [
                    'message' => 'No directive @testA defined.',
                    'locations' => [['line' => 2, 'column' => 29]],
                ],
                [
                    'message' => 'Directive @argument_definition not allowed at FIELD_DEFINITION location.',
                    'locations' => [['line' => 23, 'column' => 26], ['line' => 12, 'column' => 11]],
                ],
                [
                    'message' => 'Directive @union not allowed at INPUT_FIELD_DEFINITION location.',
                    'locations' => [['line' => 35, 'column' => 38], ['line' => 5, 'column' => 11]],
                ],
                [
                    'message' => 'Directive @object not allowed at INPUT_OBJECT location.',
                    'locations' => [['line' => 34, 'column' => 27], ['line' => 3, 'column' => 11]],
                ],
                [
                    'message' => 'Directive @interface not allowed at UNION location.',
                    'locations' => [['line' => 26, 'column' => 27], ['line' => 4, 'column' => 11]],
                ],
                [
                    'message' => 'Directive @enum_value not allowed at SCALAR location.',
                    'locations' => [['line' => 28, 'column' => 29], ['line' => 10, 'column' => 11]],
                ],
                [
                    'message' => 'Directive @enum not allowed at ENUM_VALUE location.',
                    'locations' => [['line' => 31, 'column' => 24], ['line' => 8, 'column' => 11]],
                ],
                [
                    'message' => 'Directive @input_object not allowed at ENUM location.',
                    'locations' => [['line' => 30, 'column' => 25], ['line' => 7, 'column' => 11]],
                ],
            ]
        );
    }
}
