<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\UniqueDirectiveNames;

final class UniqueDirectiveNamesTest extends ValidatorTestCase
{
    /**
     * @param array<int, array<string, mixed>> $errors
     *
     * @throws \Exception
     */
    private function expectSDLErrors(string $sdlString, ?Schema $schema, array $errors): void
    {
        $this->expectSDLErrorsFromRule(new UniqueDirectiveNames(), $sdlString, $schema, $errors);
    }

    /**
     * @see describe('Validate: Unique directive names')
     * @see it('no directive')
     */
    public function testNoDirective(): void
    {
        $this->expectValidSDL(
            new UniqueDirectiveNames(),
            '
      type Foo
            '
        );
    }

    /**
     * @see it('one directive')
     */
    public function testOneDirective(): void
    {
        $this->expectValidSDL(
            new UniqueDirectiveNames(),
            '
      directive @foo on SCHEMA
            '
        );
    }

    /**
     * @see it('many directives')
     */
    public function testManyDirectives(): void
    {
        $this->expectValidSDL(
            new UniqueDirectiveNames(),
            '
      directive @foo on SCHEMA
      directive @bar on SCHEMA
      directive @baz on SCHEMA
            '
        );
    }

    /**
     * @see it('directive and non-directive definitions named the same')
     */
    public function testDirectiveAndNonDirectiveDefinitionsNamedTheSame(): void
    {
        $this->expectValidSDL(
            new UniqueDirectiveNames(),
            '
      query foo { __typename }
      fragment foo on foo { __typename }
      type foo

      directive @foo on SCHEMA
            '
        );
    }

    /**
     * @see it('directives named the same')
     */
    public function testDirectivesNamedTheSame(): void
    {
        $this->expectSDLErrors(
            '
      directive @foo on SCHEMA

      directive @foo on SCHEMA
            ',
            null,
            [
                [
                    'message' => 'There can be only one directive named "@foo".',
                    'locations' => [
                        ['line' => 2, 'column' => 18],
                        ['line' => 4, 'column' => 18],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('adding new directive to existing schema')
     */
    public function testAddingNewDirectiveToExistingSchema(): void
    {
        $schema = BuildSchema::build('directive @foo on SCHEMA');

        $this->expectValidSDL(new UniqueDirectiveNames(), 'directive @bar on SCHEMA', $schema);
    }

    /**
     * @see it('adding new directive with standard name to existing schema')
     */
    public function testAddingNewDirectiveWithStandardNameToExistingSchema(): void
    {
        $schema = BuildSchema::build('type foo');

        $this->expectSDLErrors(
            'directive @skip on SCHEMA',
            $schema,
            [
                [
                    'message' => 'Directive "@skip" already exists in the schema. It cannot be redefined.',
                    'locations' => [
                        ['line' => 1, 'column' => 12],
                    ],
                ],
            ],
        );
    }

    /**
     * @see it('adding new directive to existing schema with same-named type')
     */
    public function testAddingNewDirectiveToExistingSchemaWithSameNamedType(): void
    {
        $schema = BuildSchema::build('type foo');

        $this->expectValidSDL(new UniqueDirectiveNames(), 'directive @foo on SCHEMA', $schema);
    }

    /**
     * @see it('adding conflicting directives to existing schema')
     */
    public function testAddingConflictingDirectivesToExistingSchema(): void
    {
        $schema = BuildSchema::build('directive @foo on SCHEMA');

        $this->expectSDLErrors(
            'directive @foo on SCHEMA',
            $schema,
            [
                [
                    'message' => 'Directive "@foo" already exists in the schema. It cannot be redefined.',
                    'locations' => [['line' => 1, 'column' => 12]],
                ],
            ],
        );
    }
}
