<?php
namespace GraphQL;

use GraphQL\Language\Parser;
use GraphQL\Validator\DocumentValidator;

class StartWarsValidationTest extends \PHPUnit_Framework_TestCase
{
    // Star Wars Validation Tests
    // Basic Queries
    public function testValidatesAComplexButValidQuery()
    {
        $query = '
        query NestedQueryWithFragment {
          hero {
            ...NameAndAppearances
            friends {
              ...NameAndAppearances
              friends {
                ...NameAndAppearances
              }
            }
          }
        }

        fragment NameAndAppearances on Character {
          name
          appearsIn
        }
      ';
        $result = $this->validationResult($query);
        $this->assertEquals(true, $result['isValid']);
    }

    public function testThatNonExistentFieldsAreInvalid()
    {
        // Notes that non-existent fields are invalid
        $query = '
        query HeroSpaceshipQuery {
          hero {
            favoriteSpaceship
          }
        }
        ';
        $this->assertEquals(false, $this->validationResult($query)['isValid']);
    }

    public function testRequiresFieldsOnObjects()
    {
        $query = '
        query HeroNoFieldsQuery {
          hero
        }
        ';
        $this->assertEquals(false, $this->validationResult($query)['isValid']);
    }

    public function testDisallowsFieldsOnScalars()
    {

      $query = '
        query HeroFieldsOnScalarQuery {
          hero {
            name {
              firstCharacterOfName
            }
          }
        }
        ';
        $this->assertEquals(false, $this->validationResult($query)['isValid']);
    }

    public function testDisallowsObjectFieldsOnInterfaces()
    {
        $query = '
        query DroidFieldOnCharacter {
          hero {
            name
            primaryFunction
          }
        }
        ';
        $this->assertEquals(false, $this->validationResult($query)['isValid']);
    }

    public function testAllowsObjectFieldsInFragments()
    {
        $query = '
        query DroidFieldInFragment {
          hero {
            name
            ...DroidFields
          }
        }

        fragment DroidFields on Droid {
          primaryFunction
        }
        ';
        $this->assertEquals(true, $this->validationResult($query)['isValid']);
    }

    public function testAllowsObjectFieldsInInlineFragments()
    {
        $query = '
        query DroidFieldInFragment {
          hero {
            name
            ... on Droid {
              primaryFunction
            }
          }
        }
        ';
        $this->assertEquals(true, $this->validationResult($query)['isValid']);
    }

    /**
     * Helper function to test a query and the expected response.
     */
    private function validationResult($query)
    {
        $ast = Parser::parse($query);
        return DocumentValidator::validate(StarWarsSchema::build(), $ast);
    }
}
