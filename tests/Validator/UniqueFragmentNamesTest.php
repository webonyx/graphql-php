<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\UniqueFragmentNames;

class UniqueFragmentNamesTest extends TestCase
{
    // Validate: Unique fragment names

    /**
     * @it no fragments
     */
    public function testNoFragments()
    {
        $this->expectPassesRule(new UniqueFragmentNames(), '
      {
        field
      }
        ');
    }

    /**
     * @it one fragment
     */
    public function testOneFragment()
    {
        $this->expectPassesRule(new UniqueFragmentNames, '
      {
        ...fragA
      }

      fragment fragA on Type {
        field
      }
        ');
    }

    /**
     * @it many fragments
     */
    public function testManyFragments()
    {
        $this->expectPassesRule(new UniqueFragmentNames, '
      {
        ...fragA
        ...fragB
        ...fragC
      }
      fragment fragA on Type {
        fieldA
      }
      fragment fragB on Type {
        fieldB
      }
      fragment fragC on Type {
        fieldC
      }
        ');
    }

    /**
     * @it inline fragments are always unique
     */
    public function testInlineFragmentsAreAlwaysUnique()
    {
        $this->expectPassesRule(new UniqueFragmentNames, '
      {
        ...on Type {
          fieldA
        }
        ...on Type {
          fieldB
        }
      }
        ');
    }

    /**
     * @it fragment and operation named the same
     */
    public function testFragmentAndOperationNamedTheSame()
    {
        $this->expectPassesRule(new UniqueFragmentNames, '
      query Foo {
        ...Foo
      }
      fragment Foo on Type {
        field
      }
        ');
    }

    /**
     * @it fragments named the same
     */
    public function testFragmentsNamedTheSame()
    {
        $this->expectFailsRule(new UniqueFragmentNames, '
      {
        ...fragA
      }
      fragment fragA on Type {
        fieldA
      }
      fragment fragA on Type {
        fieldB
      }
        ', [
            $this->duplicateFrag('fragA', 5, 16, 8, 16)
        ]);
    }

    /**
     * @it fragments named the same without being referenced
     */
    public function testFragmentsNamedTheSameWithoutBeingReferenced()
    {
        $this->expectFailsRule(new UniqueFragmentNames, '
      fragment fragA on Type {
        fieldA
      }
      fragment fragA on Type {
        fieldB
      }
        ', [
            $this->duplicateFrag('fragA', 2, 16, 5, 16)
        ]);
    }

    private function duplicateFrag($fragName, $l1, $c1, $l2, $c2)
    {
        return FormattedError::create(
            UniqueFragmentNames::duplicateFragmentNameMessage($fragName),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
