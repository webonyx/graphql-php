<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

class ValidationTest extends ValidatorTestCase
{
    // Validate: Supports full validation
    /**
     * @see it('validates queries')
     */
    public function testValidatesQueries() : void
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

    /**
     * @see it('detects bad scalar parse')
     */
    public function testDetectsBadScalarParse() : void
    {
        $doc = '
      query {
        invalidArg(arg: "bad value")
      }
        ';

        $expectedError = [
            'message'   => 'Expected type Invalid, found "bad value"; Invalid scalar is always invalid: bad value',
            'locations' => [['line' => 3, 'column' => 25]],
        ];

        $this->expectInvalid(
            self::getTestSchema(),
            null,
            $doc,
            [$expectedError]
        );
    }

    public function testPassesValidationWithEmptyRules() : void
    {
        $query = '{invalid}';

        $expectedError = [
            'message'   => 'Cannot query field "invalid" on type "QueryRoot". Did you mean "invalidArg"?',
            'locations' => [['line' => 1, 'column' => 2]],
        ];
        $this->expectFailsCompleteValidation($query, [$expectedError]);
        $this->expectValid(self::getTestSchema(), [], $query);
    }
}
