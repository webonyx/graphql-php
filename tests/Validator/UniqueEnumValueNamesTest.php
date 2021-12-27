<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\UniqueEnumValueNames;

/**
 * @see describe('Validate: Unique enum value names')
 */
class UniqueEnumValueNamesTest extends ValidatorTestCase
{
    /**
     * @see it('no values')
     */
    public function testNoValues(): void
    {
        $this->expectValidSDL(
            new UniqueEnumValueNames(),
            '
      enum SomeEnum
        '
        );
    }

    /**
     * @see it('one value')
     */
    public function testOneValue(): void
    {
        $this->expectValidSDL(
            new UniqueEnumValueNames(),
            '
      enum SomeEnum {
        FOO
      }
        '
        );
    }

    /**
     * @see it('multiple values')
     */
    public function testMultipleValues(): void
    {
        $this->expectValidSDL(
            new UniqueEnumValueNames(),
            '
      enum SomeEnum {
        FOO
        BAR
      }
        '
        );
    }

    /**
     * @see it('duplicate values inside the same enum definition')
     */
    public function testDuplicateValuesInsideTheSameEnumDefinition(): void
    {
        $this->expectSDLErrorsFromRule(
            new UniqueEnumValueNames(),
            '
      enum SomeEnum {
        FOO
        BAR
        FOO
      }
        ',
            null,
            [
                [
                    'message' => 'Enum value "SomeEnum.FOO" can only be defined once.',
                    'locations' => [
                        ['line' => 3, 'column' => 9],
                        ['line' => 5, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('extend enum with new value')
     */
    public function testExtendEnumWithNewValue(): void
    {
        $this->expectValidSDL(
            new UniqueEnumValueNames(),
            '
      enum SomeEnum {
        FOO
      }
      extend enum SomeEnum {
        BAR
      }
      extend enum SomeEnum {
        BAZ
      }
        '
        );
    }

    /**
     * @see it('extend enum with duplicate value')
     */
    public function testExtendEnumWithDuplicateValue(): void
    {
        $this->expectSDLErrorsFromRule(
            new UniqueEnumValueNames(),
            '
      extend enum SomeEnum {
        FOO
      }
      enum SomeEnum {
        FOO
      }
        ',
            null,
            [
                [
                    'message' => 'Enum value "SomeEnum.FOO" can only be defined once.',
                    'locations' => [
                        ['line' => 3, 'column' => 9],
                        ['line' => 6, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('duplicate value inside extension')
     */
    public function testDuplicateValueInsideExtension(): void
    {
        $this->expectSDLErrorsFromRule(
            new UniqueEnumValueNames(),
            '
      enum SomeEnum
      extend enum SomeEnum {
        FOO
        BAR
        FOO
      }
        ',
            null,
            [
                [
                    'message' => 'Enum value "SomeEnum.FOO" can only be defined once.',
                    'locations' => [
                        ['line' => 4, 'column' => 9],
                        ['line' => 6, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('duplicate value inside different extensions')
     */
    public function testDuplicateValueInsideDifferentExtensions(): void
    {
        $this->expectSDLErrorsFromRule(
            new UniqueEnumValueNames(),
            '
      enum SomeEnum
      extend enum SomeEnum {
        FOO
      }
      extend enum SomeEnum {
        FOO
      }
        ',
            null,
            [
                [
                    'message' => 'Enum value "SomeEnum.FOO" can only be defined once.',
                    'locations' => [
                        ['line' => 4, 'column' => 9],
                        ['line' => 7, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('adding new value to the type inside existing schema')
     */
    public function testAddingNewValueToTheTypeInsideExistingSchema(): void
    {
        $schema = BuildSchema::build('enum SomeEnum');
        $sdl = '
      extend enum SomeEnum {
        FOO
      }
        ';

        $this->expectValidSDL(
            new UniqueEnumValueNames(),
            $sdl,
            $schema,
        );
    }

    /**
     * @see it('adding conflicting value to existing schema twice')
     */
    public function testAddingConflictingValueToExistingSchemaTwice(): void
    {
        $schema = BuildSchema::build('
      enum SomeEnum {
        FOO
      }
            ');
        $sdl = '
      extend enum SomeEnum {
        FOO
      }
      extend enum SomeEnum {
        FOO
      }
        ';

        $this->expectSDLErrorsFromRule(
            new UniqueEnumValueNames(),
            $sdl,
            $schema,
            [
                [
                    'message' => 'Enum value "SomeEnum.FOO" already exists in the schema. It cannot also be defined in this type extension.',
                    'locations' => [
                        ['line' => 3, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'Enum value "SomeEnum.FOO" already exists in the schema. It cannot also be defined in this type extension.',
                    'locations' => [
                        ['line' => 6, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('adding enum values to existing schema twice')
     */
    public function testAddingEnumValuesToExistingSchemaTwice(): void
    {
        $schema = BuildSchema::build('
      enum SomeEnum
            ');
        $sdl = '
      extend enum SomeEnum {
        FOO
      }
      extend enum SomeEnum {
        FOO
      }
        ';

        $this->expectSDLErrorsFromRule(
            new UniqueEnumValueNames(),
            $sdl,
            $schema,
            [
                [
                    'message' => 'Enum value "SomeEnum.FOO" can only be defined once.',
                    'locations' => [
                        ['line' => 3, 'column' => 9],
                        ['line' => 6, 'column' => 9],
                    ],
                ],
            ],
        );
    }
}
