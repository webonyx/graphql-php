<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\FieldsOnCorrectType;

class FieldsOnCorrectTypeTest extends TestCase
{
    // Validate: Fields on correct type

    /**
     * @it Object field selection
     */
    public function testObjectFieldSelection()
    {
        $this->expectPassesRule(new FieldsOnCorrectType(), '
      fragment objectFieldSelection on Dog {
        __typename
        name
      }
        ');
    }

    /**
     * @it Aliased object field selection
     */
    public function testAliasedObjectFieldSelection()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment aliasedObjectFieldSelection on Dog {
        tn : __typename
        otherName : name
      }
        ');
    }

    /**
     * @it Interface field selection
     */
    public function testInterfaceFieldSelection()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment interfaceFieldSelection on Pet {
        __typename
        name
      }
        ');
    }

    /**
     * @it Aliased interface field selection
     */
    public function testAliasedInterfaceFieldSelection()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment interfaceFieldSelection on Pet {
        otherName : name
      }
        ');
    }

    /**
     * @it Lying alias selection
     */
    public function testLyingAliasSelection()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment lyingAliasSelection on Dog {
        name : nickname
      }
        ');
    }

    /**
     * @it Ignores fields on unknown type
     */
    public function testIgnoresFieldsOnUnknownType()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment unknownSelection on UnknownType {
        unknownField
      }
        ');
    }

    /**
     * @it reports errors when type is known again
     */
    public function testReportsErrorsWhenTypeIsKnownAgain()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment typeKnownAgain on Pet {
        unknown_pet_field {
          ... on Cat {
            unknown_cat_field
          }
        }
      }',
            [
                $this->undefinedField('unknown_pet_field', 'Pet', [], [], 3, 9),
                $this->undefinedField('unknown_cat_field', 'Cat', [], [], 5, 13)
            ]
        );
    }

    /**
     * @it Field not defined on fragment
     */
    public function testFieldNotDefinedOnFragment()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment fieldNotDefined on Dog {
        meowVolume
      }',
            [$this->undefinedField('meowVolume', 'Dog', [], ['barkVolume'], 3, 9)]
        );
    }

    /**
     * @it Ignores deeply unknown field
     */
    public function testIgnoresDeeplyUnknownField()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment deepFieldNotDefined on Dog {
        unknown_field {
          deeper_unknown_field
        }
      }',
            [$this->undefinedField('unknown_field', 'Dog', [], [], 3, 9)]
        );
    }

    /**
     * @it Sub-field not defined
     */
    public function testSubFieldNotDefined()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment subFieldNotDefined on Human {
        pets {
          unknown_field
        }
      }',
            [$this->undefinedField('unknown_field', 'Pet', [], [], 4, 11)]
        );
    }

    /**
     * @it Field not defined on inline fragment
     */
    public function testFieldNotDefinedOnInlineFragment()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment fieldNotDefined on Pet {
        ... on Dog {
          meowVolume
        }
      }',
            [$this->undefinedField('meowVolume', 'Dog', [], ['barkVolume'], 4, 11)]
        );
    }

    /**
     * @it Aliased field target not defined
     */
    public function testAliasedFieldTargetNotDefined()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment aliasedFieldTargetNotDefined on Dog {
        volume : mooVolume
      }',
            [$this->undefinedField('mooVolume', 'Dog', [], ['barkVolume'], 3, 9)]
        );
    }

    /**
     * @it Aliased lying field target not defined
     */
    public function testAliasedLyingFieldTargetNotDefined()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment aliasedLyingFieldTargetNotDefined on Dog {
        barkVolume : kawVolume
      }',
            [$this->undefinedField('kawVolume', 'Dog', [], ['barkVolume'], 3, 9)]
        );
    }

    /**
     * @it Not defined on interface
     */
    public function testNotDefinedOnInterface()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment notDefinedOnInterface on Pet {
        tailLength
      }',
            [$this->undefinedField('tailLength', 'Pet', [], [], 3, 9)]
        );
    }

    /**
     * @it Defined on implementors but not on interface
     */
    public function testDefinedOnImplmentorsButNotOnInterface()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment definedOnImplementorsButNotInterface on Pet {
        nickname
      }',
            [$this->undefinedField('nickname', 'Pet', ['Dog', 'Cat'], ['name'], 3, 9)]
        );
    }

    /**
     * @it Meta field selection on union
     */
    public function testMetaFieldSelectionOnUnion()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment directFieldSelectionOnUnion on CatOrDog {
        __typename
      }'
        );
    }

    /**
     * @it Direct field selection on union
     */
    public function testDirectFieldSelectionOnUnion()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment directFieldSelectionOnUnion on CatOrDog {
        directField
      }',
            [$this->undefinedField('directField', 'CatOrDog', [], [], 3, 9)]
        );
    }

    /**
     * @it Defined on implementors queried on union
     */
    public function testDefinedOnImplementorsQueriedOnUnion()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment definedOnImplementorsQueriedOnUnion on CatOrDog {
        name
      }',
            [$this->undefinedField(
                'name',
                'CatOrDog',
                ['Being', 'Pet', 'Canine', 'Dog', 'Cat'],
                [],
                3,
                9
            )]
        );
    }

    /**
     * @it valid field in inline fragment
     */
    public function testValidFieldInInlineFragment()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment objectFieldSelection on Pet {
        ... on Dog {
          name
        }
      }
        ');
    }

    // Describe: Fields on correct type error message

    /**
     * @it Works with no suggestions
     */
    public function testWorksWithNoSuggestions()
    {
        $this->assertEquals('Cannot query field "f" on type "T".', FieldsOnCorrectType::undefinedFieldMessage('f', 'T', [], []));
    }

    /**
     * @it Works with no small numbers of type suggestions
     */
    public function testWorksWithNoSmallNumbersOfTypeSuggestions()
    {
        $expected = 'Cannot query field "f" on type "T". ' .
            'Did you mean to use an inline fragment on "A" or "B"?';

        $this->assertEquals($expected, FieldsOnCorrectType::undefinedFieldMessage('f', 'T', ['A', 'B'], []));
    }

    /**
     * @it Works with no small numbers of field suggestions
     */
    public function testWorksWithNoSmallNumbersOfFieldSuggestions()
    {
        $expected = 'Cannot query field "f" on type "T". ' .
            'Did you mean "z" or "y"?';

        $this->assertEquals($expected, FieldsOnCorrectType::undefinedFieldMessage('f', 'T', [], ['z', 'y']));
    }

    /**
     * @it Only shows one set of suggestions at a time, preferring types
     */
    public function testOnlyShowsOneSetOfSuggestionsAtATimePreferringTypes()
    {
        $expected = 'Cannot query field "f" on type "T". ' .
            'Did you mean to use an inline fragment on "A" or "B"?';

        $this->assertEquals($expected, FieldsOnCorrectType::undefinedFieldMessage('f', 'T', ['A', 'B'], ['z', 'y']));
    }

    /**
     * @it Limits lots of type suggestions
     */
    public function testLimitsLotsOfTypeSuggestions()
    {
        $expected = 'Cannot query field "f" on type "T". ' .
            'Did you mean to use an inline fragment on "A", "B", "C", "D" or "E"?';

        $this->assertEquals($expected, FieldsOnCorrectType::undefinedFieldMessage(
            'f',
            'T',
            ['A', 'B', 'C', 'D', 'E', 'F'],
            []
        ));
    }

    /**
     * @it Limits lots of field suggestions
     */
    public function testLimitsLotsOfFieldSuggestions()
    {
        $expected = 'Cannot query field "f" on type "T". ' .
            'Did you mean "z", "y", "x", "w" or "v"?';

        $this->assertEquals($expected, FieldsOnCorrectType::undefinedFieldMessage(
            'f',
            'T',
            [],
            ['z', 'y', 'x', 'w', 'v', 'u']
        ));
    }

    private function undefinedField($field, $type, $suggestedTypes, $suggestedFields, $line, $column)
    {
        return FormattedError::create(
            FieldsOnCorrectType::undefinedFieldMessage($field, $type, $suggestedTypes, $suggestedFields),
            [new SourceLocation($line, $column)]
        );
    }
}
