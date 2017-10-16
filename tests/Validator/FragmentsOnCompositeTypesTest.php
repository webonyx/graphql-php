<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\FragmentsOnCompositeTypes;

class FragmentsOnCompositeTypesTest extends TestCase
{
    // Validate: Fragments on composite types

    /**
     * @it object is valid fragment type
     */
    public function testObjectIsValidFragmentType()
    {
        $this->expectPassesRule(new FragmentsOnCompositeTypes, '
      fragment validFragment on Dog {
        barks
      }
        ');
    }

    /**
     * @it interface is valid fragment type
     */
    public function testInterfaceIsValidFragmentType()
    {
        $this->expectPassesRule(new FragmentsOnCompositeTypes, '
      fragment validFragment on Pet {
        name
      }
        ');
    }

    /**
     * @it object is valid inline fragment type
     */
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

    /**
     * @it inline fragment without type is valid
     */
    public function testInlineFragmentWithoutTypeIsValid()
    {
        $this->expectPassesRule(new FragmentsOnCompositeTypes, '
      fragment validFragment on Pet {
        ... {
          name
        }
      }
        ');
    }

    /**
     * @it union is valid fragment type
     */
    public function testUnionIsValidFragmentType()
    {
        $this->expectPassesRule(new FragmentsOnCompositeTypes, '
      fragment validFragment on CatOrDog {
        __typename
      }
        ');
    }

    /**
     * @it scalar is invalid fragment type
     */
    public function testScalarIsInvalidFragmentType()
    {
        $this->expectFailsRule(new FragmentsOnCompositeTypes, '
      fragment scalarFragment on Boolean {
        bad
      }
        ',
            [$this->error('scalarFragment', 'Boolean', 2, 34)]);
    }

    /**
     * @it enum is invalid fragment type
     */
    public function testEnumIsInvalidFragmentType()
    {
        $this->expectFailsRule(new FragmentsOnCompositeTypes, '
      fragment scalarFragment on FurColor {
        bad
      }
        ',
            [$this->error('scalarFragment', 'FurColor', 2, 34)]);
    }

    /**
     * @it input object is invalid fragment type
     */
    public function testInputObjectIsInvalidFragmentType()
    {
        $this->expectFailsRule(new FragmentsOnCompositeTypes, '
      fragment inputFragment on ComplexInput {
        stringField
      }
        ',
            [$this->error('inputFragment', 'ComplexInput', 2, 33)]);
    }

    /**
     * @it scalar is invalid inline fragment type
     */
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
