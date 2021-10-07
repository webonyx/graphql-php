<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\KnownTypeNames;

class KnownTypeNamesTest extends ValidatorTestCase
{
    // Validate: Known type names

    /**
     * @see it('known type names are valid')
     */
    public function testKnownTypeNamesAreValid(): void
    {
        $this->expectPassesRule(
            new KnownTypeNames(),
            '
      query Foo(
        $var: String
        $required: [Int!]!
        $introspectionType: __EnumValue
      ) {
        user(id: 4) {
          pets { ... on Pet { name }, ...PetFields, ... { name } }
        }
      }
      fragment PetFields on Pet {
        name
      }
        '
        );
    }

    /**
     * @see it('unknown type names are invalid')
     */
    public function testUnknownTypeNamesAreInvalid(): void
    {
        $this->expectFailsRule(
            new KnownTypeNames(),
            '
      query Foo($var: [JumbledUpLetters!]!) {
        user(id: 4) {
          name
          pets { ... on Badger { name }, ...PetFields }
        }
      }
      fragment PetFields on Peat {
        name
      }
        ',
            [
                $this->unknownType('JumbledUpLetters', [], 2, 24),
                $this->unknownType('Badger', [], 5, 25),
                $this->unknownType('Peat', ['Pet', 'Cat'], 8, 29),
            ]
        );
    }

    /**
     * @see it('references to standard scalars that are missing in schema')
     */
    public function testReferencesToStandardScalarsThatAreMissingInSchema(): void
    {
        $schema = BuildSchema::build('type Query { foo: String }');
        $query  = '
      query ($id: ID, $float: Float, $int: Int) {
        __typename
      }
    ';
        $this->expectFailsRuleWithSchema(
            $schema,
            new KnownTypeNames(),
            $query,
            [
                $this->unknownType('Unknown type "ID".', [], 2, 19),
                $this->unknownType('Unknown type "Float".', [], 2, 31),
                $this->unknownType('Unknown type "Int".', [], 2, 44),
            ]
        );
    }

    // within SDL

    /**
     * @see it('use standard types')
     */
    public function testUseStandardTypes(): void
    {
        $this->expectValidSDL(
            new KnownTypeNames(),
            '
        type Query {
          string: String
          int: Int
          float: Float
          boolean: Boolean
          id: ID
          introspectionType: __EnumValue
        }
        '
        );
    }

    /**
     * @see it('reference types defined inside the same document')
     */
    public function testReferenceTypesDefinedInsideTheSameDocument(): void
    {
        $this->expectValidSDL(
            new KnownTypeNames(),
            '
        union SomeUnion = SomeObject | AnotherObject

        type SomeObject implements SomeInterface {
          someScalar(arg: SomeInputObject): SomeScalar
        }

        type AnotherObject {
          foo(arg: SomeInputObject): String
        }

        type SomeInterface {
          someScalar(arg: SomeInputObject): SomeScalar
        }

        input SomeInputObject {
          someScalar: SomeScalar
        }

        scalar SomeScalar

        type RootQuery {
          someInterface: SomeInterface
          someUnion: SomeUnion
          someScalar: SomeScalar
          someObject: SomeObject
        }

        schema {
          query: RootQuery
        }
        '
        );
    }

    /**
     * @see it('unknown type references')
     */
    public function testUnknownTypeReferences(): void
    {
        $this->expectSDLErrorsFromRule(
            new KnownTypeNames(),
            '
        type A
        type B

        type SomeObject implements C {
          e(d: D): E
        }

        union SomeUnion = F | G

        interface SomeInterface {
          i(h: H): I
        }

        input SomeInput {
          j: J
        }

        directive @SomeDirective(k: K) on QUERY

        schema {
          query: L
          mutation: M
          subscription: N
        }
        ',
            null,
            [
                [
                    'message' =>  'Unknown type "C". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 5, 'column' => 36 ]],
                ],
                [
                    'message' =>  'Unknown type "D". Did you mean "A", "B", or "ID"?',
                    'locations' =>  [[ 'line' => 6, 'column' => 16 ]],
                ],
                [
                    'message' =>  'Unknown type "E". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 6, 'column' => 20 ]],
                ],
                [
                    'message' =>  'Unknown type "F". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 9, 'column' => 27 ]],
                ],
                [
                    'message' =>  'Unknown type "G". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 9, 'column' => 31 ]],
                ],
                [
                    'message' =>  'Unknown type "H". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 12, 'column' => 16 ]],
                ],
                [
                    'message' =>  'Unknown type "I". Did you mean "A", "B", or "ID"?',
                    'locations' =>  [[ 'line' => 12, 'column' => 20 ]],
                ],
                [
                    'message' =>  'Unknown type "J". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 16, 'column' => 14 ]],
                ],
                [
                    'message' =>  'Unknown type "K". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 19, 'column' => 37 ]],
                ],
                [
                    'message' =>  'Unknown type "L". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 22, 'column' => 18 ]],
                ],
                [
                    'message' =>  'Unknown type "M". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 23, 'column' => 21 ]],
                ],
                [
                    'message' =>  'Unknown type "N". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 24, 'column' => 25 ]],
                ],
            ]
        );
    }

    /**
     * @see it('does not consider non-type definitions')
     */
    public function testDoesNotConsiderNonTypeDefinitions(): void
    {
        $this->expectSDLErrorsFromRule(
            new KnownTypeNames(),
            '
        query Foo { __typename }
        fragment Foo on Query { __typename }
        directive @Foo on QUERY

        type Query {
          foo: Foo
        }
        ',
            null,
            [
                [
                    'message' =>  'Unknown type "Foo".',
                    'locations' =>  [[ 'line' => 7, 'column' => 16 ]],
                ],
            ]
        );
    }

    /**
     * @see it('reference standard types inside extension document')
     */
    public function testReferenceStandardTypesInsideExtensionDocument(): void
    {
        $schema = BuildSchema::build('type Foo');
        $sdl    = '
        type SomeType {
          string: String
          int: Int
          float: Float
          boolean: Boolean
          id: ID
          introspectionType: __EnumValue
        }
        ';

        $this->expectValidSDL(
            new KnownTypeNames(),
            $sdl,
            $schema,
        );
    }

    /**
     * @see it('reference types inside extension document')
     */
    public function testReferenceTypesInsideExtensionDocument(): void
    {
        $schema = BuildSchema::build('type Foo');
        $sdl    = '
        type QueryRoot {
          foo: Foo
          bar: Bar
        }

        scalar Bar

        schema {
          query: QueryRoot
        }
        ';

        $this->expectValidSDL(
            new KnownTypeNames(),
            $sdl,
            $schema,
        );
    }

    /**
     * @see it('unknown type references inside extension document')
     */
    public function testUnknownTypeReferencesInsideExtensionDocument(): void
    {
        $schema = BuildSchema::build('type A');
        $sdl    = '
        
        type B

        type SomeObject implements C {
          e(d: D): E
        }

        union SomeUnion = F | G

        interface SomeInterface {
          i(h: H): I
        }

        input SomeInput {
          j: J
        }

        directive @SomeDirective(k: K) on QUERY

        schema {
          query: L
          mutation: M
          subscription: N
        }
        ';

        $this->expectSDLErrorsFromRule(
            new KnownTypeNames(),
            $sdl,
            $schema,
            [
                [
                    'message' =>  'Unknown type "C". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 5, 'column' => 36 ]],
                ],
                [
                    'message' =>  'Unknown type "D". Did you mean "A", "B", or "ID"?',
                    'locations' =>  [[ 'line' => 6, 'column' => 16 ]],
                ],
                [
                    'message' =>  'Unknown type "E". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 6, 'column' => 20 ]],
                ],
                [
                    'message' =>  'Unknown type "F". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 9, 'column' => 27 ]],
                ],
                [
                    'message' =>  'Unknown type "G". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 9, 'column' => 31 ]],
                ],
                [
                    'message' =>  'Unknown type "H". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 12, 'column' => 16 ]],
                ],
                [
                    'message' =>  'Unknown type "I". Did you mean "A", "B", or "ID"?',
                    'locations' =>  [[ 'line' => 12, 'column' => 20 ]],
                ],
                [
                    'message' =>  'Unknown type "J". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 16, 'column' => 14 ]],
                ],
                [
                    'message' =>  'Unknown type "K". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 19, 'column' => 37 ]],
                ],
                [
                    'message' =>  'Unknown type "L". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 22, 'column' => 18 ]],
                ],
                [
                    'message' =>  'Unknown type "M". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 23, 'column' => 21 ]],
                ],
                [
                    'message' =>  'Unknown type "N". Did you mean "A" or "B"?',
                    'locations' =>  [[ 'line' => 24, 'column' => 25 ]],
                ],
            ]
        );
    }

    /**
     * @param array<string> $suggestedTypes
     *
     * @return array{
     *     message: string,
     *     locations?: array<int, array{line: int, column: int}>
     * }
     */
    private function unknownType(string $typeName, array $suggestedTypes, int $line, int $column)
    {
        return ErrorHelper::create(
            KnownTypeNames::unknownTypeMessage($typeName, $suggestedTypes),
            [new SourceLocation($line, $column)]
        );
    }
}
