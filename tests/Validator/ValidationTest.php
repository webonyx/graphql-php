<?php
namespace GraphQL\Tests\Validator;

class ValidationTest extends TestCase
{
    // Validate: Supports full validation

    /**
     * @it validates queries
     */
    public function testValidatesQueries()
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

    public function testPassesValidationWithEmptyRules()
    {
        $query = '{invalid}';

        $expectedError = [
            'message' => 'Cannot query field "invalid" on type "QueryRoot".',
            'locations' => [ ['line' => 1, 'column' => 2] ]
        ];
        $this->expectFailsCompleteValidation($query, [$expectedError]);
        $this->expectValid($this->getDefaultSchema(), [], $query);
    }
}
