<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

class ValidationTest extends ValidatorTestCase
{
    // Validate: Supports full validation

    /**
     * @see it('validates queries')
     */
    public function testValidatesQueries(): void
    {
        $this->expectPassesCompleteValidation('
          query {
            catOrDog {
              ... on Cat {
                furColor
              }
              ... on Dog {
                isHousetrained
              }
            }
          }
        ');
    }

    public function testPassesValidationWithEmptyRules(): void
    {
        $query = '{invalid}';

        $expectedError = [
            'message' => 'Cannot query field "invalid" on type "QueryRoot". Did you mean "invalidArg"?',
            'locations' => [['line' => 1, 'column' => 2]],
        ];
        $this->expectFailsCompleteValidation($query, [$expectedError]);
        $this->expectValid(self::getTestSchema(), [], $query);
    }
}
