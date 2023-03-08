<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\UniqueTypeNames;

final class UniqueTypeNamesTest extends ValidatorTestCase
{
    /**
     * @param array<int, array<string, mixed>> $errors
     *
     * @throws \Exception
     */
    private function expectSDLErrors(string $sdlString, ?Schema $schema, array $errors): void
    {
        $this->expectSDLErrorsFromRule(new UniqueTypeNames(), $sdlString, $schema, $errors);
    }

    /**
     * @see describe('Validate: Unique type names')
     * @see it('no types')
     */
    public function testNoTypes(): void
    {
        $this->expectValidSDL(
            new UniqueTypeNames(),
            '
      directive @test on SCHEMA
            '
        );
    }

    /**
     * @see it('one type')
     */
    public function testOneType(): void
    {
        $this->expectValidSDL(
            new UniqueTypeNames(),
            '
      type Foo
            '
        );
    }

    /**
     * @see it('many types')
     */
    public function testManyTypes(): void
    {
        $this->expectValidSDL(
            new UniqueTypeNames(),
            '
      type Foo
      type Bar
      type Baz
            '
        );
    }

    /**
     * @see it('type and non-type definitions named the same')
     */
    public function testTypeAndNonTypeDefinitionsNamedTheSame(): void
    {
        $this->expectValidSDL(
            new UniqueTypeNames(),
            '
      query Foo { __typename }
      fragment Foo on Query { __typename }
      directive @Foo on SCHEMA

      type Foo
            '
        );
    }

    /**
     * @see it('types named the same')
     */
    public function testTypesNamedTheSame(): void
    {
        $this->expectSDLErrors(
            '
      type Foo

      scalar Foo
      type Foo
      interface Foo
      union Foo
      enum Foo
      input Foo
            ',
            null,
            [
                [
                    'message' => 'There can be only one type named "Foo".',
                    'locations' => [
                        ['line' => 2, 'column' => 12],
                        ['line' => 4, 'column' => 14],
                    ],
                ],
                [
                    'message' => 'There can be only one type named "Foo".',
                    'locations' => [
                        ['line' => 2, 'column' => 12],
                        ['line' => 5, 'column' => 12],
                    ],
                ],
                [
                    'message' => 'There can be only one type named "Foo".',
                    'locations' => [
                        ['line' => 2, 'column' => 12],
                        ['line' => 6, 'column' => 17],
                    ],
                ],
                [
                    'message' => 'There can be only one type named "Foo".',
                    'locations' => [
                        ['line' => 2, 'column' => 12],
                        ['line' => 7, 'column' => 13],
                    ],
                ],
                [
                    'message' => 'There can be only one type named "Foo".',
                    'locations' => [
                        ['line' => 2, 'column' => 12],
                        ['line' => 8, 'column' => 12],
                    ],
                ],
                [
                    'message' => 'There can be only one type named "Foo".',
                    'locations' => [
                        ['line' => 2, 'column' => 12],
                        ['line' => 9, 'column' => 13],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('adding new type to existing schema')
     */
    public function testAddingNewTypeToExistingSchema(): void
    {
        $schema = BuildSchema::build('type Foo');

        $this->expectValidSDL(new UniqueTypeNames(), 'type Bar', $schema);
    }

    /**
     * @see it('adding new type to existing schema with same-named directive')
     */
    public function testAddingNewTypeToExistingSchemaWithSameNamedDirective(): void
    {
        $schema = BuildSchema::build('directive @Foo on SCHEMA');

        $this->expectValidSDL(new UniqueTypeNames(), 'type Foo', $schema);
    }

    /**
     * @see it('adding conflicting types to existing schema')
     */
    public function testAddingConflictingTypesToExistingSchema(): void
    {
        $schema = BuildSchema::build('type Foo');

        $this->expectSDLErrors(
            '
      scalar Foo
      type Foo
      interface Foo
      union Foo
      enum Foo
      input Foo
            ',
            $schema,
            [
                [
                    'message' => 'Type "Foo" already exists in the schema. It cannot also be defined in this type definition.',
                    'locations' => [['line' => 2, 'column' => 14]],
                ],
                [
                    'message' => 'Type "Foo" already exists in the schema. It cannot also be defined in this type definition.',
                    'locations' => [['line' => 3, 'column' => 12]],
                ],
                [
                    'message' => 'Type "Foo" already exists in the schema. It cannot also be defined in this type definition.',
                    'locations' => [['line' => 4, 'column' => 17]],
                ],
                [
                    'message' => 'Type "Foo" already exists in the schema. It cannot also be defined in this type definition.',
                    'locations' => [['line' => 5, 'column' => 13]],
                ],
                [
                    'message' => 'Type "Foo" already exists in the schema. It cannot also be defined in this type definition.',
                    'locations' => [['line' => 6, 'column' => 12]],
                ],
                [
                    'message' => 'Type "Foo" already exists in the schema. It cannot also be defined in this type definition.',
                    'locations' => [['line' => 7, 'column' => 13]],
                ],
            ],
        );
    }
}
