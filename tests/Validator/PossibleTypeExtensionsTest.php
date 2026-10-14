<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\PossibleTypeExtensions;

final class PossibleTypeExtensionsTest extends ValidatorTestCase
{
    /**
     * @param array<int, array<string, mixed>> $errors
     *
     * @throws \Exception
     */
    private function expectSDLErrors(string $sdlString, ?Schema $schema, array $errors): void
    {
        $this->expectSDLErrorsFromRule(new PossibleTypeExtensions(), $sdlString, $schema, $errors);
    }

    /**
     * @see describe('Validate: Possible type extensions')
     * @see it('no extensions')
     */
    public function testNoExtensions(): void
    {
        $this->expectValidSDL(
            new PossibleTypeExtensions(),
            '
      scalar FooScalar
      type FooObject
      interface FooInterface
      union FooUnion
      enum FooEnum
      input FooInputObject
            '
        );
    }

    /** @see it('one extension per type') */
    public function testOneExtensionPerType(): void
    {
        $this->expectValidSDL(
            new PossibleTypeExtensions(),
            '
      scalar FooScalar
      type FooObject
      interface FooInterface
      union FooUnion
      enum FooEnum
      input FooInputObject

      extend scalar FooScalar @dummy
      extend type FooObject @dummy
      extend interface FooInterface @dummy
      extend union FooUnion @dummy
      extend enum FooEnum @dummy
      extend input FooInputObject @dummy
            '
        );
    }

    /** @see it('many extensions per type') */
    public function testManyExtensionsPerType(): void
    {
        $this->expectValidSDL(
            new PossibleTypeExtensions(),
            '
      scalar FooScalar
      type FooObject
      interface FooInterface
      union FooUnion
      enum FooEnum
      input FooInputObject

      extend scalar FooScalar @dummy
      extend type FooObject @dummy
      extend interface FooInterface @dummy
      extend union FooUnion @dummy
      extend enum FooEnum @dummy
      extend input FooInputObject @dummy

      extend scalar FooScalar @dummy
      extend type FooObject @dummy
      extend interface FooInterface @dummy
      extend union FooUnion @dummy
      extend enum FooEnum @dummy
      extend input FooInputObject @dummy
            '
        );
    }

    /** @see it('extending unknown type') */
    public function testExtendingUnknownType(): void
    {
        $message = 'Cannot extend type "Unknown" because it is not defined. Did you mean "Known"?';
        $this->expectSDLErrors(
            '
      type Known

      extend scalar Unknown @dummy
      extend type Unknown @dummy
      extend interface Unknown @dummy
      extend union Unknown @dummy
      extend enum Unknown @dummy
      extend input Unknown @dummy
            ',
            null,
            [
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 4, 'column' => 21],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 5, 'column' => 19],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 6, 'column' => 24],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 7, 'column' => 20],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 8, 'column' => 19],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 9, 'column' => 20],
                    ],
                ],
            ],
        );
    }

    /** @see it('does not consider non-type definitions') */
    public function testDoesNotConsiderNonTypeDefinitions(): void
    {
        $message = 'Cannot extend type "Foo" because it is not defined.';
        $this->expectSDLErrors(
            '
      query Foo { __typename }
      fragment Foo on Query { __typename }
      directive @Foo on SCHEMA

      extend scalar Foo @dummy
      extend type Foo @dummy
      extend interface Foo @dummy
      extend union Foo @dummy
      extend enum Foo @dummy
      extend input Foo @dummy
            ',
            null,
            [
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 6, 'column' => 21],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 7, 'column' => 19],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 8, 'column' => 24],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 9, 'column' => 20],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 10, 'column' => 19],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 11, 'column' => 20],
                    ],
                ],
            ],
        );
    }

    /** @see it('extending with different kinds') */
    public function testExtendingWithDifferentKinds(): void
    {
        $this->expectSDLErrors(
            '
      scalar FooScalar
      type FooObject
      interface FooInterface
      union FooUnion
      enum FooEnum
      input FooInputObject

      extend type FooScalar @dummy
      extend interface FooObject @dummy
      extend union FooInterface @dummy
      extend enum FooUnion @dummy
      extend input FooEnum @dummy
      extend scalar FooInputObject @dummy
            ',
            null,
            [
                [
                    'message' => 'Cannot extend non-object type "FooScalar".',
                    'locations' => [
                        ['line' => 2, 'column' => 7],
                        ['line' => 9, 'column' => 7],
                    ],
                ],
                [
                    'message' => 'Cannot extend non-interface type "FooObject".',
                    'locations' => [
                        ['line' => 3, 'column' => 7],
                        ['line' => 10, 'column' => 7],
                    ],
                ],
                [
                    'message' => 'Cannot extend non-union type "FooInterface".',
                    'locations' => [
                        ['line' => 4, 'column' => 7],
                        ['line' => 11, 'column' => 7],
                    ],
                ],
                [
                    'message' => 'Cannot extend non-enum type "FooUnion".',
                    'locations' => [
                        ['line' => 5, 'column' => 7],
                        ['line' => 12, 'column' => 7],
                    ],
                ],
                [
                    'message' => 'Cannot extend non-input object type "FooEnum".',
                    'locations' => [
                        ['line' => 6, 'column' => 7],
                        ['line' => 13, 'column' => 7],
                    ],
                ],
                [
                    'message' => 'Cannot extend non-scalar type "FooInputObject".',
                    'locations' => [
                        ['line' => 7, 'column' => 7],
                        ['line' => 14, 'column' => 7],
                    ],
                ],
            ],
        );
    }

    /** @see it('extending types within existing schema') */
    public function testExtendingTypesWithinExistingSchema(): void
    {
        $schema = BuildSchema::build('
      scalar FooScalar
      type FooObject
      interface FooInterface
      union FooUnion
      enum FooEnum
      input FooInputObject
        ');
        $sdl = '
      extend scalar FooScalar @dummy
      extend type FooObject @dummy
      extend interface FooInterface @dummy
      extend union FooUnion @dummy
      extend enum FooEnum @dummy
      extend input FooInputObject @dummy
        ';
        $this->expectValidSDL(
            new PossibleTypeExtensions(),
            $sdl,
            $schema,
        );
    }

    /** @see it('extending unknown types within existing schema') */
    public function testExtendingUnknownTypesWithinExistingSchema(): void
    {
        $schema = BuildSchema::build('type Known');
        $sdl = '
      extend scalar Unknown @dummy
      extend type Unknown @dummy
      extend interface Unknown @dummy
      extend union Unknown @dummy
      extend enum Unknown @dummy
      extend input Unknown @dummy
        ';
        $message = 'Cannot extend type "Unknown" because it is not defined. Did you mean "Known"?';
        $this->expectSDLErrors(
            $sdl,
            $schema,
            [
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 2, 'column' => 21],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 3, 'column' => 19],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 4, 'column' => 24],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 5, 'column' => 20],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 6, 'column' => 19],
                    ],
                ],
                [
                    'message' => $message,
                    'locations' => [
                        ['line' => 7, 'column' => 20],
                    ],
                ],
            ],
        );
    }

    /** @see it('extending types with different kinds within existing schema') */
    public function testExtendingTypesWithDifferentKindsWithinExistingSchema(): void
    {
        $schema = BuildSchema::build('
      scalar FooScalar
      type FooObject
      interface FooInterface
      union FooUnion
      enum FooEnum
      input FooInputObject
        ');
        $sdl = '
      extend type FooScalar @dummy
      extend interface FooObject @dummy
      extend union FooInterface @dummy
      extend enum FooUnion @dummy
      extend input FooEnum @dummy
      extend scalar FooInputObject @dummy
        ';
        $this->expectSDLErrors(
            $sdl,
            $schema,
            [
                [
                    'message' => 'Cannot extend non-object type "FooScalar".',
                    'locations' => [
                        ['line' => 2, 'column' => 7],
                    ],
                ],
                [
                    'message' => 'Cannot extend non-interface type "FooObject".',
                    'locations' => [
                        ['line' => 3, 'column' => 7],
                    ],
                ],
                [
                    'message' => 'Cannot extend non-union type "FooInterface".',
                    'locations' => [
                        ['line' => 4, 'column' => 7],
                    ],
                ],
                [
                    'message' => 'Cannot extend non-enum type "FooUnion".',
                    'locations' => [
                        ['line' => 5, 'column' => 7],
                    ],
                ],
                [
                    'message' => 'Cannot extend non-input object type "FooEnum".',
                    'locations' => [
                        ['line' => 6, 'column' => 7],
                    ],
                ],
                [
                    'message' => 'Cannot extend non-scalar type "FooInputObject".',
                    'locations' => [
                        ['line' => 7, 'column' => 7],
                    ],
                ],
            ],
        );
    }
}
