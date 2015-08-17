<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\NoUnusedFragments;

class NoUnusedFragmentsTest extends TestCase
{
    // Validate: No unused fragments
    public function testAllFragmentNamesAreUsed()
    {
        $this->expectPassesRule(new NoUnusedFragments(), '
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
        ');
    }

    public function testAllFragmentNamesAreUsedByMultipleOperations()
    {
        $this->expectPassesRule(new NoUnusedFragments, '
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
        ');
    }

    public function testContainsUnknownFragments()
    {
        $this->expectFailsRule(new NoUnusedFragments, '
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
    ', [
            $this->unusedFrag('Unused1', 22, 7),
            $this->unusedFrag('Unused2', 25, 7),
        ]);
    }

    public function testContainsUnknownFragmentsWithRefCycle()
    {
        $this->expectFailsRule(new NoUnusedFragments, '
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
    ', [
            $this->unusedFrag('Unused1', 22, 7),
            $this->unusedFrag('Unused2', 26, 7),
        ]);
    }

    public function testContainsUnknownAndUndefFragments()
    {

        $this->expectFailsRule(new NoUnusedFragments, '
      query Foo {
        human(id: 4) {
          ...bar
        }
      }
      fragment foo on Human {
        name
      }
    ', [
            $this->unusedFrag('foo', 7, 7),
        ]);
    }

    private function unusedFrag($fragName, $line, $column)
    {
        return FormattedError::create(
            NoUnusedFragments::unusedFragMessage($fragName),
            [new SourceLocation($line, $column)]
        );
    }
}
