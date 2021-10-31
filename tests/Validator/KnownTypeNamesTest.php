<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

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
      query Foo($var: JumbledUpLetters) {
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
                [
                    'message' => 'Unknown type "JumbledUpLetters".',
                    'locations' => [['line' => 2, 'column' => 23]],
                ],
                [
                    'message' => 'Unknown type "Badger".',
                    'locations' => [['line' => 5, 'column' => 25]],
                ],
                [
                    'message' => 'Unknown type "Peat". Did you mean "Pet" or "Cat"?',
                    'locations' => [['line' => 8, 'column' => 29]],
                ],
            ]
        );
    }

    /**
     * @see it('references to standard scalars that are missing in schema')
     */
    public function testReferencesToStandardScalarsThatAreMissingInSchema(): void
    {
        self::markTestSkipped('Missing implementation for SDL validation for now');
        $schema = BuildSchema::build('type Query { foo: String }');
        $query = '
      query ($id: ID, $float: Float, $int: Int) {
        __typename
      }
    ';
        $this->expectFailsRuleWithSchema(
            $schema,
            new KnownTypeNames(),
            $query,
            [
                [
                    'message' => 'Unknown type "ID".',
                    'locations' => [['line' => 2, 'column' => 19]],
                ],
                [
                    'message' => 'Unknown type "Float".',
                    'locations' => [['line' => 2, 'column' => 31]],
                ],
                [
                    'message' => 'Unknown type "Int".',
                    'locations' => [['line' => 2, 'column' => 44]],
                ],
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
        self::markTestSkipped('Missing implementation for SDL validation for now');
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
                    'message' => 'Unknown type "C". Did you mean "A" or "B"?',
                    'locations' => [['line' => 5, 'column' => 36]],
                ],
                [
                    'message' => 'Unknown type "D". Did you mean "A", "B", or "ID"?',
                    'locations' => [['line' => 6, 'column' => 16]],
                ],
                [
                    'message' => 'Unknown type "E". Did you mean "A" or "B"?',
                    'locations' => [['line' => 6, 'column' => 20]],
                ],
                [
                    'message' => 'Unknown type "F". Did you mean "A" or "B"?',
                    'locations' => [['line' => 9, 'column' => 27]],
                ],
                [
                    'message' => 'Unknown type "G". Did you mean "A" or "B"?',
                    'locations' => [['line' => 9, 'column' => 31]],
                ],
                [
                    'message' => 'Unknown type "H". Did you mean "A" or "B"?',
                    'locations' => [['line' => 12, 'column' => 16]],
                ],
                [
                    'message' => 'Unknown type "I". Did you mean "A", "B", or "ID"?',
                    'locations' => [['line' => 12, 'column' => 20]],
                ],
                [
                    'message' => 'Unknown type "J". Did you mean "A" or "B"?',
                    'locations' => [['line' => 16, 'column' => 14]],
                ],
                [
                    'message' => 'Unknown type "K". Did you mean "A" or "B"?',
                    'locations' => [['line' => 19, 'column' => 37]],
                ],
                [
                    'message' => 'Unknown type "L". Did you mean "A" or "B"?',
                    'locations' => [['line' => 22, 'column' => 18]],
                ],
                [
                    'message' => 'Unknown type "M". Did you mean "A" or "B"?',
                    'locations' => [['line' => 23, 'column' => 21]],
                ],
                [
                    'message' => 'Unknown type "N". Did you mean "A" or "B"?',
                    'locations' => [['line' => 24, 'column' => 25]],
                ],
            ]
        );
    }

    /**
     * @see it('does not consider non-type definitions')
     */
    public function testDoesNotConsiderNonTypeDefinitions(): void
    {
        self::markTestSkipped('Missing implementation for SDL validation for now');
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
                    'message' => 'Unknown type "Foo".',
                    'locations' => [['line' => 7, 'column' => 16]],
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
        $sdl = '
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
        $sdl = '
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
        self::markTestSkipped('Missing implementation for SDL validation for now');
        $schema = BuildSchema::build('type A');
        $sdl = '
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
                    'message' => 'Unknown type "C". Did you mean "A" or "B"?',
                    'locations' => [['line' => 4, 'column' => 36]],
                ],
                [
                    'message' => 'Unknown type "D". Did you mean "A", "B", or "ID"?',
                    'locations' => [['line' => 5, 'column' => 16]],
                ],
                [
                    'message' => 'Unknown type "E". Did you mean "A" or "B"?',
                    'locations' => [['line' => 5, 'column' => 20]],
                ],
                [
                    'message' => 'Unknown type "F". Did you mean "A" or "B"?',
                    'locations' => [['line' => 8, 'column' => 27]],
                ],
                [
                    'message' => 'Unknown type "G". Did you mean "A" or "B"?',
                    'locations' => [['line' => 8, 'column' => 31]],
                ],
                [
                    'message' => 'Unknown type "H". Did you mean "A" or "B"?',
                    'locations' => [['line' => 11, 'column' => 16]],
                ],
                [
                    'message' => 'Unknown type "I". Did you mean "A", "B", or "ID"?',
                    'locations' => [['line' => 11, 'column' => 20]],
                ],
                [
                    'message' => 'Unknown type "J". Did you mean "A" or "B"?',
                    'locations' => [['line' => 15, 'column' => 14]],
                ],
                [
                    'message' => 'Unknown type "K". Did you mean "A" or "B"?',
                    'locations' => [['line' => 18, 'column' => 37]],
                ],
                [
                    'message' => 'Unknown type "L". Did you mean "A" or "B"?',
                    'locations' => [['line' => 21, 'column' => 18]],
                ],
                [
                    'message' => 'Unknown type "M". Did you mean "A" or "B"?',
                    'locations' => [['line' => 22, 'column' => 21]],
                ],
                [
                    'message' => 'Unknown type "N". Did you mean "A" or "B"?',
                    'locations' => [['line' => 23, 'column' => 25]],
                ],
            ]
        );
    }
}
