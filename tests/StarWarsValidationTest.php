<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Error\Error;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\Parser;
use GraphQL\Validator\DocumentValidator;
use PHPUnit\Framework\TestCase;

final class StarWarsValidationTest extends TestCase
{
    // Star Wars Validation Tests
    // Basic Queries

    /** @see it('Validates a complex but valid query') */
    public function testValidatesAComplexButValidQuery(): void
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
        self::assertCount(0, $errors);
    }

    /**
     * Helper function to test a query and the expected response.
     *
     * @throws \Exception
     * @throws \JsonException
     * @throws SyntaxError
     *
     * @return array<int, Error>
     */
    private function validationErrors(string $query): array
    {
        $ast = Parser::parse($query);
        $schema = StarWarsSchema::build();

        return DocumentValidator::validate($schema, $ast);
    }

    /** @see it('Notes that non-existent fields are invalid') */
    public function testThatNonExistentFieldsAreInvalid(): void
    {
        $query = '
        query HeroSpaceshipQuery {
          hero {
            favoriteSpaceship
          }
        }
        ';
        $errors = $this->validationErrors($query);
        self::assertCount(1, $errors);
    }

    public function testThatInvisibleFieldsAreInvalid(): void
    {
        $query = '
        query HeroSpaceshipQuery {
          hero {
            secretName
          }
        }
        ';
        $errors = $this->validationErrors($query);
        self::assertCount(1, $errors);
    }

    /** @see it('Requires fields on objects') */
    public function testRequiresFieldsOnObjects(): void
    {
        $query = '
        query HeroNoFieldsQuery {
          hero
        }
        ';

        $errors = $this->validationErrors($query);
        self::assertCount(1, $errors);
    }

    /** @see it('Disallows fields on scalars') */
    public function testDisallowsFieldsOnScalars(): void
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
        self::assertCount(1, $errors);
    }

    /** @see it('Disallows object fields on interfaces') */
    public function testDisallowsObjectFieldsOnInterfaces(): void
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
        self::assertCount(1, $errors);
    }

    /** @see it('Allows object fields in fragments') */
    public function testAllowsObjectFieldsInFragments(): void
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
        self::assertCount(0, $errors);
    }

    /** @see it('Allows object fields in inline fragments') */
    public function testAllowsObjectFieldsInInlineFragments(): void
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
        self::assertCount(0, $errors);
    }
}
