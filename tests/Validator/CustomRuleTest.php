<?php

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Tests\Validator\CustomRuleTest\CustomExecutableDefinitions;
use GraphQL\Validator\DocumentValidator;

class CustomRuleTest extends ValidatorTestCase
{
    public function testAddCustomRule(): void
    {
        DocumentValidator::addRule(new CustomExecutableDefinitions);

        $this->expectInvalid(
            self::getTestSchema(),
            null,
            '
      query Foo {
        dog {
          name
        }
      }
      
      type Cow {
        name: String
      }
        ',
            [
                ErrorHelper::create(
                    CustomExecutableDefinitions::nonExecutableDefinitionMessage('Cow'),
                    [new SourceLocation(8, 12)]
                )
            ]
        );
    }
}
