<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\Error;
use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\CustomValidationRule;
use GraphQL\Validator\Rules\ExecutableDefinitions;
use GraphQL\Validator\ValidationContext;

final class CustomRuleTest extends ValidatorTestCase
{
    private const CUSTOM_VALIDATION_RULE_ERROR = 'This is the error we are looking for!';

    public function testAddRuleCanReplaceDefaultRules(): void
    {
        DocumentValidator::addRule(new class() extends ExecutableDefinitions {
            public function getName(): string
            {
                return ExecutableDefinitions::class;
            }

            public static function nonExecutableDefinitionMessage(string $defName): string
            {
                return "Custom message including {$defName}";
            }
        });

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
                    'Custom message including Cow',
                    [new SourceLocation(8, 12)]
                ),
            ]
        );
    }

    public function testAddRuleUsingCustomValidationRule(): void
    {
        $customRule = new CustomValidationRule('MyRule', static function (ValidationContext $context): array {
            $context->reportError(new Error(self::CUSTOM_VALIDATION_RULE_ERROR));

            return [];
        });

        DocumentValidator::addRule($customRule);

        $this->expectInvalid(
            self::getTestSchema(),
            null,
            '
      query Foo {
        dog {
          name
        }
      }
        ',
            [
                ErrorHelper::create(self::CUSTOM_VALIDATION_RULE_ERROR),
            ]
        );

        DocumentValidator::removeRule($customRule);
    }
}
