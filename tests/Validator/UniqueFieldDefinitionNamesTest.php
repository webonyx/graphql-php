<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\UniqueFieldDefinitionNames;

class UniqueFieldDefinitionNamesTest extends ValidatorTestCase
{
    /**
     * @param array<int, array<string, mixed>> $errors
     */
    private function expectSDLErrors(string $sdlString, ?Schema $schema, array $errors): void
    {
        $this->expectSDLErrorsFromRule(new UniqueFieldDefinitionNames(), $sdlString, $schema, $errors);
    }

    /**
     * @see describe('Validate: Unique field definition names')
     * @see it('no fields')
     */
    public function testNoFields(): void
    {
        $this->expectValidSDL(
            new UniqueFieldDefinitionNames(),
            '
      type SomeObject
      interface SomeInterface
      input SomeInputObject
            '
        );
    }

    /**
     * @see it('one field')
     */
    public function testOneField(): void
    {
        $this->expectValidSDL(
            new UniqueFieldDefinitionNames(),
            '
      type SomeObject {
        foo: String
      }

      interface SomeInterface {
        foo: String
      }

      input SomeInputObject {
        foo: String
      }
            '
        );
    }

    /**
     * @see it('multiple fields')
     */
    public function testMultipleFields(): void
    {
        $this->expectValidSDL(
            new UniqueFieldDefinitionNames(),
            '
      type SomeObject {
        foo: String
        bar: String
      }

      interface SomeInterface {
        foo: String
        bar: String
      }

      input SomeInputObject {
        foo: String
        bar: String
      }
            '
        );
    }

    /**
     * @see it('duplicate fields inside the same type definition')
     */
    public function testDuplicateFieldsInsideTheSameTypeDefinition(): void
    {
        $this->expectSDLErrors(
            '
      type SomeObject {
        foo: String
        bar: String
        foo: String
      }

      interface SomeInterface {
        foo: String
        bar: String
        foo: String
      }

      input SomeInputObject {
        foo: String
        bar: String
        foo: String
      }
            ',
            null,
            [
                [
                    'message' => 'Field "SomeObject.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 3, 'column' => 9],
                        ['line' => 5, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Field "SomeInterface.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 9, 'column' => 9],
                        ['line' => 11, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Field "SomeInputObject.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 15, 'column' => 9],
                        ['line' => 17, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('extend type with new field')
     */
    public function testExtendTypeWithNewField(): void
    {
        $this->expectValidSDL(
            new UniqueFieldDefinitionNames(),
            '
      type SomeObject {
        foo: String
      }
      extend type SomeObject {
        bar: String
      }
      extend type SomeObject {
        baz: String
      }

      interface SomeInterface {
        foo: String
      }
      extend interface SomeInterface {
        bar: String
      }
      extend interface SomeInterface {
        baz: String
      }

      input SomeInputObject {
        foo: String
      }
      extend input SomeInputObject {
        bar: String
      }
      extend input SomeInputObject {
        baz: String
      }
            '
        );
    }

    /**
     * @see it('extend type with duplicate field')
     */
    public function testExtendTypeWithDuplicateField(): void
    {
        $this->expectSDLErrors(
            '
      extend type SomeObject {
        foo: String
      }
      type SomeObject {
        foo: String
      }

      extend interface SomeInterface {
        foo: String
      }
      interface SomeInterface {
        foo: String
      }

      extend input SomeInputObject {
        foo: String
      }
      input SomeInputObject {
        foo: String
      }
            ',
            null,
            [
                [
                    'message' => 'Field "SomeObject.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 3, 'column' => 9],
                        ['line' => 6, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Field "SomeInterface.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 10, 'column' => 9],
                        ['line' => 13, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Field "SomeInputObject.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 17, 'column' => 9],
                        ['line' => 20, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('duplicate field inside extension')
     */
    public function testDuplicateFieldInsideExtension(): void
    {
        $this->expectSDLErrors(
            '
      type SomeObject
      extend type SomeObject {
        foo: String
        bar: String
        foo: String
      }

      interface SomeInterface
      extend interface SomeInterface {
        foo: String
        bar: String
        foo: String
      }

      input SomeInputObject
      extend input SomeInputObject {
        foo: String
        bar: String
        foo: String
      }
            ',
            null,
            [
                [
                    'message' => 'Field "SomeObject.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 4, 'column' => 9],
                        ['line' => 6, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Field "SomeInterface.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 11, 'column' => 9],
                        ['line' => 13, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Field "SomeInputObject.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 18, 'column' => 9],
                        ['line' => 20, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('duplicate field inside different extensions')
     */
    public function testDuplicateFieldInsideDifferentExtensions(): void
    {
        $this->expectSDLErrors(
            '
      type SomeObject
      extend type SomeObject {
        foo: String
      }
      extend type SomeObject {
        foo: String
      }

      interface SomeInterface
      extend interface SomeInterface {
        foo: String
      }
      extend interface SomeInterface {
        foo: String
      }

      input SomeInputObject
      extend input SomeInputObject {
        foo: String
      }
      extend input SomeInputObject {
        foo: String
      }
            ',
            null,
            [
                [
                    'message' => 'Field "SomeObject.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 4, 'column' => 9],
                        ['line' => 7, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Field "SomeInterface.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 12, 'column' => 9],
                        ['line' => 15, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Field "SomeInputObject.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 20, 'column' => 9],
                        ['line' => 23, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('adding new field to the type inside existing schema')
     */
    public function testAddingNewFieldToTheTypeInsideExistingSchema(): void
    {
        $schema = BuildSchema::build('        
      type SomeObject
      interface SomeInterface
      input SomeInputObject
        ');
        $sdl = '
      extend type SomeObject {
        foo: String
      }

      extend interface SomeInterface {
        foo: String
      }

      extend input SomeInputObject {
        foo: String
      }
            ';
        $this->expectValidSDL(
            new UniqueFieldDefinitionNames(),
            $sdl,
            $schema,
        );
    }

    /**
     * @see it('adding conflicting fields to existing schema twice')
     */
    public function testAddingConflictingFieldsToExistingSchemaTwice(): void
    {
        $schema = BuildSchema::build('
      type SomeObject {
        foo: String
      }

      interface SomeInterface {
        foo: String
      }

      input SomeInputObject {
        foo: String
      }
        ');
        $sdl = '
      extend type SomeObject {
        foo: String
      }
      extend interface SomeInterface {
        foo: String
      }
      extend input SomeInputObject {
        foo: String
      }

      extend type SomeObject {
        foo: String
      }
      extend interface SomeInterface {
        foo: String
      }
      extend input SomeInputObject {
        foo: String
      }
            ';
        $this->expectSDLErrors(
            $sdl,
            $schema,
            [
                [
                    'message' => 'Field "SomeObject.foo" already exists in the schema. It cannot also be defined in this type extension.',
                    'locations' => [['line' => 3, 'column' => 9]],
                ],
                [
                    'message' => 'Field "SomeInterface.foo" already exists in the schema. It cannot also be defined in this type extension.',
                    'locations' => [['line' => 6, 'column' => 9]],
                ],
                [
                    'message' => 'Field "SomeInputObject.foo" already exists in the schema. It cannot also be defined in this type extension.',
                    'locations' => [['line' => 9, 'column' => 9]],
                ],
                [
                    'message' => 'Field "SomeObject.foo" already exists in the schema. It cannot also be defined in this type extension.',
                    'locations' => [['line' => 13, 'column' => 9]],
                ],
                [
                    'message' => 'Field "SomeInterface.foo" already exists in the schema. It cannot also be defined in this type extension.',
                    'locations' => [['line' => 16, 'column' => 9]],
                ],
                [
                    'message' => 'Field "SomeInputObject.foo" already exists in the schema. It cannot also be defined in this type extension.',
                    'locations' => [['line' => 19, 'column' => 9]],
                ],
            ],
        );
    }

    /**
     * @see it('adding fields to existing schema twice')
     */
    public function testAddingFieldsToExistingSchemaTwice(): void
    {
        $schema = BuildSchema::build('
      type SomeObject
      interface SomeInterface
      input SomeInputObject
        ');
        $sdl = '
      extend type SomeObject {
        foo: String
      }
      extend type SomeObject {
        foo: String
      }

      extend interface SomeInterface {
        foo: String
      }
      extend interface SomeInterface {
        foo: String
      }

      extend input SomeInputObject {
        foo: String
      }
      extend input SomeInputObject {
        foo: String
      }
            ';
        $this->expectSDLErrors(
            $sdl,
            $schema,
            [
                [
                    'message' => 'Field "SomeObject.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 3, 'column' => 9],
                        ['line' => 6, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Field "SomeInterface.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 10, 'column' => 9],
                        ['line' => 13, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Field "SomeInputObject.foo" can only be defined once.',
                    'locations' => [
                        ['line' => 17, 'column' => 9],
                        ['line' => 20, 'column' => 9],
                    ],
                ],
            ],
        );
    }
}
