<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\FieldsOnCorrectType;

class FieldsOnCorrectTypeTest extends ValidatorTestCase
{
    // Validate: Fields on correct type

    /**
     * @see it('Object field selection')
     */
    public function testObjectFieldSelection(): void
    {
        $this->expectPassesRule(
            new FieldsOnCorrectType(),
            '
      fragment objectFieldSelection on Dog {
        __typename
        name
      }
        '
        );
    }

    /**
     * @see it('Aliased object field selection')
     */
    public function testAliasedObjectFieldSelection(): void
    {
        $this->expectPassesRule(
            new FieldsOnCorrectType(),
            '
      fragment aliasedObjectFieldSelection on Dog {
        tn : __typename
        otherName : name
      }
        '
        );
    }

    /**
     * @see it('Interface field selection')
     */
    public function testInterfaceFieldSelection(): void
    {
        $this->expectPassesRule(
            new FieldsOnCorrectType(),
            '
      fragment interfaceFieldSelection on Pet {
        __typename
        name
      }
        '
        );
    }

    /**
     * @see it('Aliased interface field selection')
     */
    public function testAliasedInterfaceFieldSelection(): void
    {
        $this->expectPassesRule(
            new FieldsOnCorrectType(),
            '
      fragment interfaceFieldSelection on Pet {
        otherName : name
      }
        '
        );
    }

    /**
     * @see it('Lying alias selection')
     */
    public function testLyingAliasSelection(): void
    {
        $this->expectPassesRule(
            new FieldsOnCorrectType(),
            '
      fragment lyingAliasSelection on Dog {
        name : nickname
      }
        '
        );
    }

    /**
     * @see it('Ignores fields on unknown type')
     */
    public function testIgnoresFieldsOnUnknownType(): void
    {
        $this->expectPassesRule(
            new FieldsOnCorrectType(),
            '
      fragment unknownSelection on UnknownType {
        unknownField
      }
        '
        );
    }

    /**
     * @see it('reports errors when type is known again')
     */
    public function testReportsErrorsWhenTypeIsKnownAgain(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment typeKnownAgain on Pet {
        unknown_pet_field {
          ... on Cat {
            unknown_cat_field
          }
        }
      }',
            [
                $this->undefinedField('unknown_pet_field', 'Pet', [], [], 3, 9),
                $this->undefinedField('unknown_cat_field', 'Cat', [], [], 5, 13),
            ]
        );
    }

    private function undefinedField($field, $type, $suggestedTypes, $suggestedFields, $line, $column)
    {
        return ErrorHelper::create(
            FieldsOnCorrectType::undefinedFieldMessage($field, $type, $suggestedTypes, $suggestedFields),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('Field not defined on fragment')
     */
    public function testFieldNotDefinedOnFragment(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment fieldNotDefined on Dog {
        meowVolume
      }',
            [$this->undefinedField('meowVolume', 'Dog', [], ['barkVolume'], 3, 9)]
        );
    }

    /**
     * @see it('Ignores deeply unknown field')
     */
    public function testIgnoresDeeplyUnknownField(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment deepFieldNotDefined on Dog {
        unknown_field {
          deeper_unknown_field
        }
      }',
            [$this->undefinedField('unknown_field', 'Dog', [], [], 3, 9)]
        );
    }

    /**
     * @see it('Sub-field not defined')
     */
    public function testSubFieldNotDefined(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment subFieldNotDefined on Human {
        pets {
          unknown_field
        }
      }',
            [$this->undefinedField('unknown_field', 'Pet', [], [], 4, 11)]
        );
    }

    /**
     * @see it('Field not defined on inline fragment')
     */
    public function testFieldNotDefinedOnInlineFragment(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment fieldNotDefined on Pet {
        ... on Dog {
          meowVolume
        }
      }',
            [$this->undefinedField('meowVolume', 'Dog', [], ['barkVolume'], 4, 11)]
        );
    }

    /**
     * @see it('Aliased field target not defined')
     */
    public function testAliasedFieldTargetNotDefined(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment aliasedFieldTargetNotDefined on Dog {
        volume : mooVolume
      }',
            [$this->undefinedField('mooVolume', 'Dog', [], ['barkVolume'], 3, 9)]
        );
    }

    /**
     * @see it('Aliased lying field target not defined')
     */
    public function testAliasedLyingFieldTargetNotDefined(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment aliasedLyingFieldTargetNotDefined on Dog {
        barkVolume : kawVolume
      }',
            [$this->undefinedField('kawVolume', 'Dog', [], ['barkVolume'], 3, 9)]
        );
    }

    /**
     * @see it('Not defined on interface')
     */
    public function testNotDefinedOnInterface(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment notDefinedOnInterface on Pet {
        tailLength
      }',
            [$this->undefinedField('tailLength', 'Pet', [], [], 3, 9)]
        );
    }

    /**
     * @see it('Defined on implementors but not on interface')
     */
    public function testDefinedOnImplementorsButNotOnInterface(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment definedOnImplementorsButNotInterface on Pet {
        nickname
      }',
            [$this->undefinedField('nickname', 'Pet', ['Dog', 'Cat'], ['name'], 3, 9)]
        );
    }

    /**
     * @see it('Meta field selection on union')
     */
    public function testMetaFieldSelectionOnUnion(): void
    {
        $this->expectPassesRule(
            new FieldsOnCorrectType(),
            '
      fragment directFieldSelectionOnUnion on CatOrDog {
        __typename
      }'
        );
    }

    /**
     * @see it('Direct field selection on union')
     */
    public function testDirectFieldSelectionOnUnion(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment directFieldSelectionOnUnion on CatOrDog {
        directField
      }',
            [$this->undefinedField('directField', 'CatOrDog', [], [], 3, 9)]
        );
    }

    /**
     * @see it('Defined on implementors queried on union')
     */
    public function testDefinedOnImplementorsQueriedOnUnion(): void
    {
        $this->expectFailsRule(
            new FieldsOnCorrectType(),
            '
      fragment definedOnImplementorsQueriedOnUnion on CatOrDog {
        name
      }',
            [
                $this->undefinedField(
                    'name',
                    'CatOrDog',
                    ['Being', 'Pet', 'Canine', 'Dog', 'Cat'],
                    [],
                    3,
                    9
                ),
            ]
        );
    }

    // Describe: Fields on correct type error message

    /**
     * @see it('valid field in inline fragment')
     */
    public function testValidFieldInInlineFragment(): void
    {
        $this->expectPassesRule(
            new FieldsOnCorrectType(),
            '
      fragment objectFieldSelection on Pet {
        ... on Dog {
          name
        }
      }
        '
        );
    }

    /**
     * @see it('Works with no suggestions')
     */
    public function testWorksWithNoSuggestions(): void
    {
        self::assertEquals(
            'Cannot query field "f" on type "T".',
            FieldsOnCorrectType::undefinedFieldMessage('f', 'T', [], [])
        );
    }

    /**
     * @see it('Works with no small numbers of type suggestions')
     */
    public function testWorksWithNoSmallNumbersOfTypeSuggestions(): void
    {
        $expected = 'Cannot query field "f" on type "T". ' .
            'Did you mean to use an inline fragment on "A" or "B"?';

        self::assertEquals($expected, FieldsOnCorrectType::undefinedFieldMessage('f', 'T', ['A', 'B'], []));
    }

    /**
     * @see it('Works with no small numbers of field suggestions')
     */
    public function testWorksWithNoSmallNumbersOfFieldSuggestions(): void
    {
        $expected = 'Cannot query field "f" on type "T". ' .
            'Did you mean "z" or "y"?';

        self::assertEquals($expected, FieldsOnCorrectType::undefinedFieldMessage('f', 'T', [], ['z', 'y']));
    }

    /**
     * @see it('Only shows one set of suggestions at a time, preferring types')
     */
    public function testOnlyShowsOneSetOfSuggestionsAtATimePreferringTypes(): void
    {
        $expected = 'Cannot query field "f" on type "T". ' .
            'Did you mean to use an inline fragment on "A" or "B"?';

        self::assertEquals($expected, FieldsOnCorrectType::undefinedFieldMessage('f', 'T', ['A', 'B'], ['z', 'y']));
    }

    /**
     * @see it('Limits lots of type suggestions')
     */
    public function testLimitsLotsOfTypeSuggestions(): void
    {
        $expected = 'Cannot query field "f" on type "T". ' .
            'Did you mean to use an inline fragment on "A", "B", "C", "D", or "E"?';

        self::assertEquals(
            $expected,
            FieldsOnCorrectType::undefinedFieldMessage(
                'f',
                'T',
                ['A', 'B', 'C', 'D', 'E', 'F'],
                []
            )
        );
    }

    /**
     * @see it('Limits lots of field suggestions')
     */
    public function testLimitsLotsOfFieldSuggestions(): void
    {
        $expected = 'Cannot query field "f" on type "T". ' .
            'Did you mean "z", "y", "x", "w", or "v"?';

        self::assertEquals(
            $expected,
            FieldsOnCorrectType::undefinedFieldMessage(
                'f',
                'T',
                [],
                ['z', 'y', 'x', 'w', 'v', 'u']
            )
        );
    }
}
