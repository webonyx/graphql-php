<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\NoUnusedFragments;

class NoUnusedFragmentsTest extends ValidatorTestCase
{
    // Validate: No unused fragments

    /**
     * @see it('all fragment names are used')
     */
    public function testAllFragmentNamesAreUsed() : void
    {
        $this->expectPassesRule(
            new NoUnusedFragments(),
            '
      {
        human(id: 4) {
          ...HumanFields1
          ... on Human {
            ...HumanFields2
          }
        }
      }
      fragment HumanFields1 on Human {
        name
        ...HumanFields3
      }
      fragment HumanFields2 on Human {
        name
      }
      fragment HumanFields3 on Human {
        name
      }
        '
        );
    }

    /**
     * @see it('all fragment names are used by multiple operations')
     */
    public function testAllFragmentNamesAreUsedByMultipleOperations() : void
    {
        $this->expectPassesRule(
            new NoUnusedFragments(),
            '
      query Foo {
        human(id: 4) {
          ...HumanFields1
        }
      }
      query Bar {
        human(id: 4) {
          ...HumanFields2
        }
      }
      fragment HumanFields1 on Human {
        name
        ...HumanFields3
      }
      fragment HumanFields2 on Human {
        name
      }
      fragment HumanFields3 on Human {
        name
      }
        '
        );
    }

    /**
     * @see it('contains unknown fragments')
     */
    public function testContainsUnknownFragments() : void
    {
        $this->expectFailsRule(
            new NoUnusedFragments(),
            '
      query Foo {
        human(id: 4) {
          ...HumanFields1
        }
      }
      query Bar {
        human(id: 4) {
          ...HumanFields2
        }
      }
      fragment HumanFields1 on Human {
        name
        ...HumanFields3
      }
      fragment HumanFields2 on Human {
        name
      }
      fragment HumanFields3 on Human {
        name
      }
      fragment Unused1 on Human {
        name
      }
      fragment Unused2 on Human {
        name
      }
    ',
            [
                $this->unusedFrag('Unused1', 22, 7),
                $this->unusedFrag('Unused2', 25, 7),
            ]
        );
    }

    private function unusedFrag($fragName, $line, $column)
    {
        return FormattedError::create(
            NoUnusedFragments::unusedFragMessage($fragName),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('contains unknown fragments with ref cycle')
     */
    public function testContainsUnknownFragmentsWithRefCycle() : void
    {
        $this->expectFailsRule(
            new NoUnusedFragments(),
            '
      query Foo {
        human(id: 4) {
          ...HumanFields1
        }
      }
      query Bar {
        human(id: 4) {
          ...HumanFields2
        }
      }
      fragment HumanFields1 on Human {
        name
        ...HumanFields3
      }
      fragment HumanFields2 on Human {
        name
      }
      fragment HumanFields3 on Human {
        name
      }
      fragment Unused1 on Human {
        name
        ...Unused2
      }
      fragment Unused2 on Human {
        name
        ...Unused1
      }
    ',
            [
                $this->unusedFrag('Unused1', 22, 7),
                $this->unusedFrag('Unused2', 26, 7),
            ]
        );
    }

    /**
     * @see it('contains unknown and undef fragments')
     */
    public function testContainsUnknownAndUndefFragments() : void
    {
        $this->expectFailsRule(
            new NoUnusedFragments(),
            '
      query Foo {
        human(id: 4) {
          ...bar
        }
      }
      fragment foo on Human {
        name
      }
    ',
            [
                $this->unusedFrag('foo', 7, 7),
            ]
        );
    }
}
