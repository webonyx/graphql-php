<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\NoInvisibleFields;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
final class NoInvisibleFieldsTest extends ValidatorTestCase
{
    // Validate: No invisible fields

    public function testPassesValidationOnVisibleField(): void
    {
        $this->expectPassesRule(
            new NoInvisibleFields(),
            '
      fragment objectFieldSelection on Dog {
        __typename
        name
      }
        '
        );
    }

    public function testFailsValidationOnInvisibleField(): void
    {
        $this->expectFailsRule(
            new NoInvisibleFields(),
            '
      fragment objectFieldSelection on Dog {
        __typename
        secretName
      }
        ',
            [
                [
                    'message' => 'Cannot query field "secretName" on type "Dog".',
                ],
            ]
        );
    }
}
