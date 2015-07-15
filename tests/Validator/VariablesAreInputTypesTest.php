<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\VariablesAreInputTypes;

class VariablesAreInputTypesTest extends TestCase
{
    // Validate: Variables are input types
    public function testInputTypesAreValid()
    {
        $this->expectPassesRule(new VariablesAreInputTypes(), '
      query Foo($a: String, $b: [Boolean!]!, $c: ComplexInput) {
        field(a: $a, b: $b, c: $c)
      }
        ');
    }

    public function testOutputTypesAreInvalid()
    {
        $this->expectFailsRule(new VariablesAreInputTypes, '
      query Foo($a: Dog, $b: [[DogOrCat!]]!, $c: Pet) {
        field(a: $a, b: $b, c: $c)
      }
        ', [
                new FormattedError(
                    Messages::nonInputTypeOnVarMessage('a', 'Dog'),
                    [new SourceLocation(2, 21)]
                ),
                new FormattedError(
                    Messages::nonInputTypeOnVarMessage('b', '[[DogOrCat!]]!'),
                    [new SourceLocation(2, 30)]
                ),
                new FormattedError(
                    Messages::nonInputTypeOnVarMessage('c', 'Pet'),
                    [new SourceLocation(2, 50)]
                )
            ]
        );
    }
}