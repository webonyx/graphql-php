<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\UniqueFragmentNames;

class UniqueFragmentNamesTest extends ValidatorTestCase
{
    // Validate: Unique fragment names
    /**
     * @see it('no fragments')
     */
    public function testNoFragments() : void
    {
        $this->expectPassesRule(
            new UniqueFragmentNames(),
            '
      {
        field
      }
        '
        );
    }

    /**
     * @see it('one fragment')
     */
    public function testOneFragment() : void
    {
        $this->expectPassesRule(
            new UniqueFragmentNames(),
            '
      {
        ...fragA
      }

      fragment fragA on Type {
        field
      }
        '
        );
    }

    /**
     * @see it('many fragments')
     */
    public function testManyFragments() : void
    {
        $this->expectPassesRule(
            new UniqueFragmentNames(),
            '
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
        '
        );
    }

    /**
     * @see it('inline fragments are always unique')
     */
    public function testInlineFragmentsAreAlwaysUnique() : void
    {
        $this->expectPassesRule(
            new UniqueFragmentNames(),
            '
      {
        ...on Type {
          fieldA
        }
        ...on Type {
          fieldB
        }
      }
        '
        );
    }

    /**
     * @see it('fragment and operation named the same')
     */
    public function testFragmentAndOperationNamedTheSame() : void
    {
        $this->expectPassesRule(
            new UniqueFragmentNames(),
            '
      query Foo {
        ...Foo
      }
      fragment Foo on Type {
        field
      }
        '
        );
    }

    /**
     * @see it('fragments named the same')
     */
    public function testFragmentsNamedTheSame() : void
    {
        $this->expectFailsRule(
            new UniqueFragmentNames(),
            '
      {
        ...fragA
      }
      fragment fragA on Type {
        fieldA
      }
      fragment fragA on Type {
        fieldB
      }
        ',
            [$this->duplicateFrag('fragA', 5, 16, 8, 16)]
        );
    }

    private function duplicateFrag($fragName, $l1, $c1, $l2, $c2)
    {
        return FormattedError::create(
            UniqueFragmentNames::duplicateFragmentNameMessage($fragName),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }

    /**
     * @see it('fragments named the same without being referenced')
     */
    public function testFragmentsNamedTheSameWithoutBeingReferenced() : void
    {
        $this->expectFailsRule(
            new UniqueFragmentNames(),
            '
      fragment fragA on Type {
        fieldA
      }
      fragment fragA on Type {
        fieldB
      }
        ',
            [$this->duplicateFrag('fragA', 2, 16, 5, 16)]
        );
    }
}
