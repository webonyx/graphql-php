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
        $errors = $this->validationErrors($query);
        $this->assertEquals(true, empty($errors));
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
        $errors = $this->validationErrors($query);
        $this->assertEquals(false, empty($errors));
    }

    public function testRequiresFieldsOnObjects()
    {
        $query = '
        query HeroNoFieldsQuery {
          hero
        }
        ';

        $errors = $this->validationErrors($query);
        $this->assertEquals(false, empty($errors));
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
        $errors = $this->validationErrors($query);
        $this->assertEquals(false, empty($errors));
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
        $errors = $this->validationErrors($query);
        $this->assertEquals(false, empty($errors));
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
        $errors = $this->validationErrors($query);
        $this->assertEquals(true, empty($errors));
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
        $errors = $this->validationErrors($query);
        $this->assertEquals(true, empty($errors));
    }

    /**
     * Helper function to test a query and the expected response.
     */
    private function validationErrors($query)
    {
        $ast = Parser::parse($query);
        return DocumentValidator::validate(StarWarsSchema::build(), $ast);
    }
}
