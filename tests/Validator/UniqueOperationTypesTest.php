<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\UniqueOperationTypes;

class UniqueOperationTypesTest extends ValidatorTestCase
{
    private function expectSDLErrors(string $sdlString, ?Schema $schema, array $errors): void
    {
        $this->expectSDLErrorsFromRule(new UniqueOperationTypes(), $sdlString, $schema, $errors);
    }

    /**
     * @see describe('Validate: Unique operation types')
     * @see it('no schema definition')
     */
    public function testNoSchemaDefinition(): void
    {
        $this->expectValidSDL(
            new UniqueOperationTypes(),
            '
      type Foo
            '
        );
    }

    /**
     * @see it('schema definition with all types')
     */
    public function testSchemaDefinitionWithAllTypes(): void
    {
        $this->expectValidSDL(
            new UniqueOperationTypes(),
            '
      type Foo

      schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }
            '
        );
    }

    /**
     * @see it('schema definition with single extension')
     */
    public function testSchemaDefinitionWithSingleExtension(): void
    {
        $this->expectValidSDL(
            new UniqueOperationTypes(),
            '
      type Foo

      schema { query: Foo }

      extend schema {
        mutation: Foo
        subscription: Foo
      }
            '
        );
    }

    /**
     * @see it('schema definition with separate extensions')
     */
    public function testSchemaDefinitionWithSeparateExtensions(): void
    {
        $this->expectValidSDL(
            new UniqueOperationTypes(),
            '
      type Foo

      schema { query: Foo }
      extend schema { mutation: Foo }
      extend schema { subscription: Foo }
            '
        );
    }

    /**
     * @see it('extend schema before definition')
     */
    public function testExtendSchemaBeforeDefinition(): void
    {
        $this->expectValidSDL(
            new UniqueOperationTypes(),
            '
      type Foo

      extend schema { mutation: Foo }
      extend schema { subscription: Foo }

      schema { query: Foo }
            '
        );
    }

    /**
     * @see it('duplicate operation types inside single schema definition')
     */
    public function testDuplicateOperationTypesInsideSingleSchemaDefinition(): void
    {
        $this->expectSDLErrors(
            '
      type Foo

      schema {
        query: Foo
        mutation: Foo
        subscription: Foo

        query: Foo
        mutation: Foo
        subscription: Foo
      }
            ',
            null,
            [
                [
                    'message' => 'There can be only one query type in schema.',
                    'locations' => [
                        ['line' => 5, 'column' => 9],
                        ['line' => 9, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one mutation type in schema.',
                    'locations' => [
                        ['line' => 6, 'column' => 9],
                        ['line' => 10, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one subscription type in schema.',
                    'locations' => [
                        ['line' => 7, 'column' => 9],
                        ['line' => 11, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('duplicate operation types inside schema extension')
     */
    public function testDuplicateOperationTypesInsideSchemaExtension(): void
    {
        $this->expectSDLErrors(
            '
      type Foo

      schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }

      extend schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }
            ',
            null,
            [
                [
                    'message' => 'There can be only one query type in schema.',
                    'locations' => [
                        ['line' => 5, 'column' => 9],
                        ['line' => 11, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one mutation type in schema.',
                    'locations' => [
                        ['line' => 6, 'column' => 9],
                        ['line' => 12, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one subscription type in schema.',
                    'locations' => [
                        ['line' => 7, 'column' => 9],
                        ['line' => 13, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('duplicate operation types inside schema extension twice')
     */
    public function testDuplicateOperationTypesInsideSchemaExtensionTwice(): void
    {
        $this->expectSDLErrors(
            '
      type Foo

      schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }

      extend schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }

      extend schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }
            ',
            null,
            [
                [
                    'message' => 'There can be only one query type in schema.',
                    'locations' => [
                        ['line' => 5, 'column' => 9],
                        ['line' => 11, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one mutation type in schema.',
                    'locations' => [
                        ['line' => 6, 'column' => 9],
                        ['line' => 12, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one subscription type in schema.',
                    'locations' => [
                        ['line' => 7, 'column' => 9],
                        ['line' => 13, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one query type in schema.',
                    'locations' => [
                        ['line' => 5, 'column' => 9],
                        ['line' => 17, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one mutation type in schema.',
                    'locations' => [
                        ['line' => 6, 'column' => 9],
                        ['line' => 18, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one subscription type in schema.',
                    'locations' => [
                        ['line' => 7, 'column' => 9],
                        ['line' => 19, 'column' => 9],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('duplicate operation types inside second schema extension')
     */
    public function testDuplicateOperationTypesInsideSecondSchemaExtension(): void
    {
        $this->expectSDLErrors(
            '
      type Foo

      schema {
        query: Foo
      }

      extend schema {
        mutation: Foo
        subscription: Foo
      }

      extend schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }
            ',
            null,
            [
                [
                    'message' => 'There can be only one query type in schema.',
                    'locations' => [
                        ['line' => 5, 'column' => 9],
                        ['line' => 14, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one mutation type in schema.',
                    'locations' => [
                        ['line' => 9, 'column' => 9],
                        ['line' => 15, 'column' => 9],
                    ],
                ],
                [
                    'message' => 'There can be only one subscription type in schema.',
                    'locations' => [
                        ['line' => 10, 'column' => 9],
                        ['line' => 16, 'column' => 9],
                    ],
                ],

            ],
        );
    }

    /**
     * @see it('define schema inside extension SDL')
     */
    public function testDefineSchemaInsideExtensionSdl(): void
    {
        $schema = BuildSchema::build('type Foo');
        $sdl    = '
      schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }
        ';

        $this->expectValidSDL(new UniqueOperationTypes(), $sdl, $schema);
    }

    /**
     * @see it('define and extend schema inside extension SDL')
     */
    public function testDefineAndExtendSchemaInsideExtensionSdl(): void
    {
        $schema = BuildSchema::build('type Foo');
        $sdl    = '
      schema { query: Foo }
      extend schema { mutation: Foo }
      extend schema { subscription: Foo }
        ';

        $this->expectValidSDL(new UniqueOperationTypes(), $sdl, $schema);
    }

    /**
     * @see it('adding new operation types to existing schema')
     */
    public function testAddingNewOperationTypesToExistingSchema(): void
    {
        $schema = BuildSchema::build('type Query');
        $sdl    = '
      extend schema { mutation: Foo }
      extend schema { subscription: Foo }
        ';

        $this->expectValidSDL(new UniqueOperationTypes(), $sdl, $schema);
    }

    /**
     * @see it('adding conflicting operation types to existing schema')
     */
    public function testAddingConflictingOperationTypesToExistingSchema(): void
    {
        $schema = BuildSchema::build('
      type Query
      type Mutation
      type Subscription

      type Foo
        ');

        $sdl = '
      extend schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }
        ';

        $this->expectSDLErrors($sdl, $schema, [
            [
                'message' => 'Type for query already defined in the schema. It cannot be redefined.',
                'locations' => [['line' => 3, 'column' => 9]],
            ],
            [
                'message' => 'Type for mutation already defined in the schema. It cannot be redefined.',
                'locations' => [['line' => 4, 'column' => 9]],
            ],
            [
                'message' => 'Type for subscription already defined in the schema. It cannot be redefined.',
                'locations' => [['line' => 5, 'column' => 9]],
            ],
        ]);
    }

    /**
     * @see it('adding conflicting operation types to existing schema twice')
     */
    public function testAddingConflictingOperationTypesToExistingSchemaTwice(): void
    {
        $schema = BuildSchema::build('
      type Query
      type Mutation
      type Subscription
        ');

        $sdl = '
      extend schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }

      extend schema {
        query: Foo
        mutation: Foo
        subscription: Foo
      }
        ';

        $this->expectSDLErrors($sdl, $schema, [
            [
                'message' => 'Type for query already defined in the schema. It cannot be redefined.',
                'locations' => [['line' => 3, 'column' => 9]],
            ],
            [
                'message' => 'Type for mutation already defined in the schema. It cannot be redefined.',
                'locations' => [['line' => 4, 'column' => 9]],
            ],
            [
                'message' => 'Type for subscription already defined in the schema. It cannot be redefined.',
                'locations' => [['line' => 5, 'column' => 9]],
            ],
            [
                'message' => 'Type for query already defined in the schema. It cannot be redefined.',
                'locations' => [['line' => 9, 'column' => 9]],
            ],
            [
                'message' => 'Type for mutation already defined in the schema. It cannot be redefined.',
                'locations' => [['line' => 10, 'column' => 9]],
            ],
            [
                'message' => 'Type for subscription already defined in the schema. It cannot be redefined.',
                'locations' => [['line' => 11, 'column' => 9]],
            ],
        ]);
    }
}
