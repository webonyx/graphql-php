<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\UniqueArgumentDefinitionNames;

final class UniqueArgumentDefinitionNamesTest extends ValidatorTestCase
{
    /**
     * @param array<int, array<string, mixed>> $errors
     *
     * @throws \Exception
     */
    private function expectSDLErrors(string $sdlString, ?Schema $schema, array $errors): void
    {
        $this->expectSDLErrorsFromRule(new UniqueArgumentDefinitionNames(), $sdlString, $schema, $errors);
    }

    /**
     * @see describe('Validate: Unique argument definition names')
     * @see it('no args')
     */
    public function testNoArgs(): void
    {
        $this->expectValidSDL(
            new UniqueArgumentDefinitionNames(),
            '
      type SomeObject {
        someField: String
      }

      interface SomeInterface {
        someField: String
      }

      directive @someDirective on QUERY
            '
        );
    }

    /** @see it('one argument') */
    public function testOneArgument(): void
    {
        $this->expectValidSDL(
            new UniqueArgumentDefinitionNames(),
            '
      type SomeObject {
        someField(foo: String): String
      }

      interface SomeInterface {
        someField(foo: String): String
      }

      directive @someDirective(foo: String) on QUERY
            '
        );
    }

    /** @see it('multiple arguments') */
    public function testMultipleArguments(): void
    {
        $this->expectValidSDL(
            new UniqueArgumentDefinitionNames(),
            '
      type SomeObject {
        someField(
          foo: String
          bar: String
        ): String
      }

      interface SomeInterface {
        someField(
          foo: String
          bar: String
        ): String
      }

      directive @someDirective(
        foo: String
        bar: String
      ) on QUERY
            '
        );
    }

    /** @see it('duplicating arguments') */
    public function testDuplicatingArguments(): void
    {
        $this->expectSDLErrors(
            '
      type SomeObject {
        someField(
          foo: String
          bar: String
          foo: String
        ): String
      }

      interface SomeInterface {
        someField(
          foo: String
          bar: String
          foo: String
        ): String
      }

      directive @someDirective(
        foo: String
        bar: String
        foo: String
      ) on QUERY
            ',
            null,
            [
                [
                    'message' => 'Argument "SomeObject.someField(foo:)" can only be defined once.',
                    'locations' => [
                        ['line' => 4, 'column' => 11],
                        ['line' => 6, 'column' => 11],
                    ],
                ],
                [
                    'message' => 'Argument "SomeInterface.someField(foo:)" can only be defined once.',
                    'locations' => [
                        ['line' => 12, 'column' => 11],
                        ['line' => 14, 'column' => 11],
                    ],
                ],
                [
                    'message' => 'Argument "@someDirective(foo:)" can only be defined once.',
                    'locations' => [
                        ['line' => 19, 'column' => 9],
                        ['line' => 21, 'column' => 9],
                    ],
                ],
            ],
        );
    }
}
