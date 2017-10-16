<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\VariablesAreInputTypes;

class VariablesAreInputTypesTest extends TestCase
{
    // Validate: Variables are input types

    /**
     * @it input types are valid
     */
    public function testInputTypesAreValid()
    {
        $this->expectPassesRule(new VariablesAreInputTypes(), '
      query Foo($a: String, $b: [Boolean!]!, $c: ComplexInput) {
        field(a: $a, b: $b, c: $c)
      }
        ');
    }

    /**
     * @it output types are invalid
     */
    public function testOutputTypesAreInvalid()
    {
        $this->expectFailsRule(new VariablesAreInputTypes, '
      query Foo($a: Dog, $b: [[CatOrDog!]]!, $c: Pet) {
        field(a: $a, b: $b, c: $c)
      }
        ', [
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
                )
            ]
        );
    }
}
