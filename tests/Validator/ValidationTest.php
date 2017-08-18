<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;

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
/*
    public function testAllowsSettingRulesGlobally()
    {
        $rule = new QueryComplexity(0);

        DocumentValidator::addRule($rule);
        $instance = DocumentValidator::getRule(QueryComplexity::class);
        $this->assertSame($rule, $instance);
    }
*/
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
