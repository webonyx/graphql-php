<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\VariablesAreInputTypes;

class VariablesAreInputTypesTest extends ValidatorTestCase
{
    // Validate: Variables are input types
    /**
     * @see it('input types are valid')
     */
    public function testInputTypesAreValid() : void
    {
        $this->expectPassesRule(
            new VariablesAreInputTypes(),
            '
      query Foo($a: String, $b: [Boolean!]!, $c: ComplexInput) {
        field(a: $a, b: $b, c: $c)
      }
        '
        );
    }

    /**
     * @see it('output types are invalid')
     */
    public function testOutputTypesAreInvalid() : void
    {
        $this->expectFailsRule(
            new VariablesAreInputTypes(),
            '
      query Foo($a: Dog, $b: [[CatOrDog!]]!, $c: Pet) {
        field(a: $a, b: $b, c: $c)
      }
        ',
            [
                FormattedError::create(
                    VariablesAreInputTypes::nonInputTypeOnVarMessage('a', 'Dog'),
                    [new SourceLocation(2, 21)]
                ),
                FormattedError::create(
                    VariablesAreInputTypes::nonInputTypeOnVarMessage('b', '[[CatOrDog!]]!'),
                    [new SourceLocation(2, 30)]
                ),
                FormattedError::create(
                    VariablesAreInputTypes::nonInputTypeOnVarMessage('c', 'Pet'),
                    [new SourceLocation(2, 50)]
                ),
            ]
        );
    }
}
