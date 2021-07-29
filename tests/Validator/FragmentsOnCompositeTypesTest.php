<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\FragmentsOnCompositeTypes;

class FragmentsOnCompositeTypesTest extends ValidatorTestCase
{
    // Validate: Fragments on composite types

    /**
     * @see it('object is valid fragment type')
     */
    public function testObjectIsValidFragmentType(): void
    {
        $this->expectPassesRule(
            new FragmentsOnCompositeTypes(),
            '
      fragment validFragment on Dog {
        barks
      }
        '
        );
    }

    /**
     * @see it('interface is valid fragment type')
     */
    public function testInterfaceIsValidFragmentType(): void
    {
        $this->expectPassesRule(
            new FragmentsOnCompositeTypes(),
            '
      fragment validFragment on Pet {
        name
      }
        '
        );
    }

    /**
     * @see it('object is valid inline fragment type')
     */
    public function testObjectIsValidInlineFragmentType(): void
    {
        $this->expectPassesRule(
            new FragmentsOnCompositeTypes(),
            '
      fragment validFragment on Pet {
        ... on Dog {
          barks
        }
      }
        '
        );
    }

    /**
     * @see it('interface is valid inline fragment type')
     */
    public function testInterfaceIsValidInlineFragmentType(): void
    {
        $this->expectPassesRule(
            new FragmentsOnCompositeTypes(),
            '
      fragment validFragment on Mammal {
        ... on Canine {
          name
        }
      }
        '
        );
    }

    /**
     * @see it('inline fragment without type is valid')
     */
    public function testInlineFragmentWithoutTypeIsValid(): void
    {
        $this->expectPassesRule(
            new FragmentsOnCompositeTypes(),
            '
      fragment validFragment on Pet {
        ... {
          name
        }
      }
        '
        );
    }

    /**
     * @see it('union is valid fragment type')
     */
    public function testUnionIsValidFragmentType(): void
    {
        $this->expectPassesRule(
            new FragmentsOnCompositeTypes(),
            '
      fragment validFragment on CatOrDog {
        __typename
      }
        '
        );
    }

    /**
     * @see it('scalar is invalid fragment type')
     */
    public function testScalarIsInvalidFragmentType(): void
    {
        $this->expectFailsRule(
            new FragmentsOnCompositeTypes(),
            '
      fragment scalarFragment on Boolean {
        bad
      }
        ',
            [$this->error('scalarFragment', 'Boolean', 2, 34)]
        );
    }

    private function error($fragName, $typeName, $line, $column)
    {
        return ErrorHelper::create(
            FragmentsOnCompositeTypes::fragmentOnNonCompositeErrorMessage($fragName, $typeName),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('enum is invalid fragment type')
     */
    public function testEnumIsInvalidFragmentType(): void
    {
        $this->expectFailsRule(
            new FragmentsOnCompositeTypes(),
            '
      fragment scalarFragment on FurColor {
        bad
      }
        ',
            [$this->error('scalarFragment', 'FurColor', 2, 34)]
        );
    }

    /**
     * @see it('input object is invalid fragment type')
     */
    public function testInputObjectIsInvalidFragmentType(): void
    {
        $this->expectFailsRule(
            new FragmentsOnCompositeTypes(),
            '
      fragment inputFragment on ComplexInput {
        stringField
      }
        ',
            [$this->error('inputFragment', 'ComplexInput', 2, 33)]
        );
    }

    /**
     * @see it('scalar is invalid inline fragment type')
     */
    public function testScalarIsInvalidInlineFragmentType(): void
    {
        $this->expectFailsRule(
            new FragmentsOnCompositeTypes(),
            '
      fragment invalidFragment on Pet {
        ... on String {
          barks
        }
      }
        ',
            [
                ErrorHelper::create(
                    FragmentsOnCompositeTypes::inlineFragmentOnNonCompositeErrorMessage('String'),
                    [new SourceLocation(3, 16)]
                ),
            ]
        );
    }
}
