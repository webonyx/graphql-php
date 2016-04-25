<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Language\Parser;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\DocumentValidator;

class ValidationText extends TestCase
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
}
