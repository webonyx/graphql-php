<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\KnownTypeNames;

class KnownTypeNamesTest extends TestCase
{
    // Validate: Known type names

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

    private function unknownType($typeName, $line, $column)
    {
        return FormattedError::create(
            KnownTypeNames::unknownTypeMessage($typeName),
            [new SourceLocation($line, $column)]
        );
    }
}
