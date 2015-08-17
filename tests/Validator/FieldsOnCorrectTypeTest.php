<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\FieldsOnCorrectType;

class FieldsOnCorrectTypeTest extends TestCase
{
    // Validate: Fields on correct type
    public function testObjectFieldSelection()
    {
        $this->expectPassesRule(new FieldsOnCorrectType(), '
      fragment objectFieldSelection on Dog {
        __typename
        name
      }
        ');
    }

    public function testAliasedObjectFieldSelection()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment aliasedObjectFieldSelection on Dog {
        tn : __typename
        otherName : name
      }
        ');
    }

    public function testInterfaceFieldSelection()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment interfaceFieldSelection on Pet {
        __typename
        name
      }
        ');
    }

    public function testAliasedInterfaceFieldSelection()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment interfaceFieldSelection on Pet {
        otherName : name
      }
        ');
    }

    public function testLyingAliasSelection()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment lyingAliasSelection on Dog {
        name : nickname
      }
        ');
    }

    public function testIgnoresFieldsOnUnknownType()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment unknownSelection on UnknownType {
        unknownField
      }
        ');
    }

    public function testFieldNotDefinedOnFragment()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment fieldNotDefined on Dog {
        meowVolume
      }',
            [$this->undefinedField('meowVolume', 'Dog', 3, 9)]
        );
    }

    public function testFieldNotDefinedDeeplyOnlyReportsFirst()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment deepFieldNotDefined on Dog {
        unknown_field {
          deeper_unknown_field
        }
      }',
            [$this->undefinedField('unknown_field', 'Dog', 3, 9)]
        );
    }

    public function testSubFieldNotDefined()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment subFieldNotDefined on Human {
        pets {
          unknown_field
        }
      }',
            [$this->undefinedField('unknown_field', 'Pet', 4, 11)]
        );
    }

    public function testFieldNotDefinedOnInlineFragment()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment fieldNotDefined on Pet {
        ... on Dog {
          meowVolume
        }
      }',
            [$this->undefinedField('meowVolume', 'Dog', 4, 11)]
        );
    }

    public function testAliasedFieldTargetNotDefined()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment aliasedFieldTargetNotDefined on Dog {
        volume : mooVolume
      }',
            [$this->undefinedField('mooVolume', 'Dog', 3, 9)]
        );
    }

    public function testAliasedLyingFieldTargetNotDefined()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment aliasedLyingFieldTargetNotDefined on Dog {
        barkVolume : kawVolume
      }',
            [$this->undefinedField('kawVolume', 'Dog', 3, 9)]
        );
    }

    public function testNotDefinedOnInterface()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment notDefinedOnInterface on Pet {
        tailLength
      }',
            [$this->undefinedField('tailLength', 'Pet', 3, 9)]
        );
    }

    public function testDefinedOnImplmentorsButNotOnInterface()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment definedOnImplementorsButNotInterface on Pet {
        nickname
      }',
            [$this->undefinedField('nickname', 'Pet', 3, 9)]
        );
    }

    public function testMetaFieldSelectionOnUnion()
    {
        $this->expectPassesRule(new FieldsOnCorrectType, '
      fragment directFieldSelectionOnUnion on CatOrDog {
        __typename
      }'
        );
    }

    public function testDirectFieldSelectionOnUnion()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment directFieldSelectionOnUnion on CatOrDog {
        directField
      }',
            [$this->undefinedField('directField', 'CatOrDog', 3, 9)]
        );
    }

    public function testDefinedOnImplementorsQueriedOnUnion()
    {
        $this->expectFailsRule(new FieldsOnCorrectType, '
      fragment definedOnImplementorsQueriedOnUnion on CatOrDog {
        name
      }',
            [$this->undefinedField('name', 'CatOrDog', 3, 9)]
        );
    }

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

    private function undefinedField($field, $type, $line, $column)
    {
        return FormattedError::create(
            Messages::undefinedFieldMessage($field, $type),
            [new SourceLocation($line, $column)]
        );
    }
}
