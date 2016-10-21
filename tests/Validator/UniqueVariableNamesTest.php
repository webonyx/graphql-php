<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\UniqueVariableNames;

class UniqueVariableNamesTest extends TestCase
{
    // Validate: Unique variable names

    /**
     * @it unique variable names
     */
    public function testUniqueVariableNames()
    {
        $this->expectPassesRule(new UniqueVariableNames(), '
      query A($x: Int, $y: String) { __typename }
      query B($x: String, $y: Int) { __typename }
        ');
    }

    /**
     * @it duplicate variable names
     */
    public function testDuplicateVariableNames()
    {
        $this->expectFailsRule(new UniqueVariableNames, '
      query A($x: Int, $x: Int, $x: String) { __typename }
      query B($x: String, $x: Int) { __typename }
      query C($x: Int, $x: Int) { __typename }
        ', [
            $this->duplicateVariable('x', 2, 16, 2, 25),
            $this->duplicateVariable('x', 2, 16, 2, 34),
            $this->duplicateVariable('x', 3, 16, 3, 28),
            $this->duplicateVariable('x', 4, 16, 4, 25)
        ]);
    }

    private function duplicateVariable($name, $l1, $c1, $l2, $c2)
    {
        return FormattedError::create(
            UniqueVariableNames::duplicateVariableMessage($name),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
