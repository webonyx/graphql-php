<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\FragmentsOnCompositeTypes;

class FragmentsOnCompositeTypesTest extends TestCase
{
    // Validate: Fragments on composite types

    public function testObjectIsValidFragmentType()
    {
        $this->expectPassesRule(new FragmentsOnCompositeTypes, '
      fragment validFragment on Dog {
        barks
      }
        ');
    }

    public function testInterfaceIsValidFragmentType()
    {
        $this->expectPassesRule(new FragmentsOnCompositeTypes, '
      fragment validFragment on Pet {
        name
      }
        ');
    }

    public function testObjectIsValidInlineFragmentType()
    {
        $this->expectPassesRule(new FragmentsOnCompositeTypes, '
      fragment validFragment on Pet {
        ... on Dog {
          barks
        }
      }
        ');
    }

    public function testUnionIsValidFragmentType()
    {
        $this->expectPassesRule(new FragmentsOnCompositeTypes, '
      fragment validFragment on CatOrDog {
        __typename
      }
        ');
    }

    public function testScalarIsInvalidFragmentType()
    {
        $this->expectFailsRule(new FragmentsOnCompositeTypes, '
      fragment scalarFragment on Boolean {
        bad
      }
        ',
            [$this->error('scalarFragment', 'Boolean', 2, 34)]);
    }

    public function testEnumIsInvalidFragmentType()
    {
        $this->expectFailsRule(new FragmentsOnCompositeTypes, '
      fragment scalarFragment on FurColor {
        bad
      }
        ',
            [$this->error('scalarFragment', 'FurColor', 2, 34)]);
    }

    public function testInputObjectIsInvalidFragmentType()
    {
        $this->expectFailsRule(new FragmentsOnCompositeTypes, '
      fragment inputFragment on ComplexInput {
        stringField
      }
        ',
            [$this->error('inputFragment', 'ComplexInput', 2, 33)]);
    }

    public function testScalarIsInvalidInlineFragmentType()
    {
        $this->expectFailsRule(new FragmentsOnCompositeTypes, '
      fragment invalidFragment on Pet {
        ... on String {
          barks
        }
      }
        ',
        [FormattedError::create(
            FragmentsOnCompositeTypes::inlineFragmentOnNonCompositeErrorMessage('String'),
            [new SourceLocation(3, 16)]
        )]
        );
    }

    private function error($fragName, $typeName, $line, $column)
    {
        return FormattedError::create(
            FragmentsOnCompositeTypes::fragmentOnNonCompositeErrorMessage($fragName, $typeName),
            [ new SourceLocation($line, $column) ]
        );
    }
}
