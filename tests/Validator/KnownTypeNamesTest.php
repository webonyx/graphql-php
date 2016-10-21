<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\KnownTypeNames;

class KnownTypeNamesTest extends TestCase
{
    // Validate: Known type names

    /**
     * @it known type names are valid
     */
    public function testKnownTypeNamesAreValid()
    {
        $this->expectPassesRule(new KnownTypeNames, '
      query Foo($var: String, $required: [String!]!) {
        user(id: 4) {
          pets { ... on Pet { name }, ...PetFields }
        }
      }
      fragment PetFields on Pet {
        name
      }
        ');
    }

    /**
     * @it unknown type names are invalid
     */
    public function testUnknownTypeNamesAreInvalid()
    {
        $this->expectFailsRule(new KnownTypeNames, '
      query Foo($var: JumbledUpLetters) {
        user(id: 4) {
          name
          pets { ... on Badger { name }, ...PetFields }
        }
      }
      fragment PetFields on Peettt {
        name
      }
        ', [
            $this->unknownType('JumbledUpLetters', 2, 23),
            $this->unknownType('Badger', 5, 25),
            $this->unknownType('Peettt', 8, 29)
        ]);
    }

    /**
     * @it ignores type definitions
     */
    public function testIgnoresTypeDefinitions()
    {
        $this->expectFailsRule(new KnownTypeNames, '
      type NotInTheSchema {
        field: FooBar
      }
      interface FooBar {
        field: NotInTheSchema
      }
      union U = A | B
      input Blob {
        field: UnknownType
      }
      query Foo($var: NotInTheSchema) {
        user(id: $var) {
          id
        }
      }
    ', [
            $this->unknownType('NotInTheSchema', 12, 23),
        ]);
    }

    private function unknownType($typeName, $line, $column)
    {
        return FormattedError::create(
            KnownTypeNames::unknownTypeMessage($typeName),
            [new SourceLocation($line, $column)]
        );
    }
}
