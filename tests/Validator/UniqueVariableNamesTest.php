<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\UniqueVariableNames;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
class UniqueVariableNamesTest extends ValidatorTestCase
{
    // Validate: Unique variable names

    /**
     * @see it('unique variable names')
     */
    public function testUniqueVariableNames(): void
    {
        $this->expectPassesRule(
            new UniqueVariableNames(),
            '
      query A($x: Int, $y: String) { __typename }
      query B($x: String, $y: Int) { __typename }
        '
        );
    }

    /**
     * @see it('duplicate variable names')
     */
    public function testDuplicateVariableNames(): void
    {
        $this->expectFailsRule(
            new UniqueVariableNames(),
            '
      query A($x: Int, $x: Int, $x: String) { __typename }
      query B($x: String, $x: Int) { __typename }
      query C($x: Int, $x: Int) { __typename }
        ',
            [
                $this->duplicateVariable('x', 2, 16, 2, 25),
                $this->duplicateVariable('x', 2, 16, 2, 34),
                $this->duplicateVariable('x', 3, 16, 3, 28),
                $this->duplicateVariable('x', 4, 16, 4, 25),
            ]
        );
    }

    /**
     * @phpstan-return ErrorArray
     */
    private function duplicateVariable(string $name, int $l1, int $c1, int $l2, int $c2): array
    {
        return ErrorHelper::create(
            UniqueVariableNames::duplicateVariableMessage($name),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
