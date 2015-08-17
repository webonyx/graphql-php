<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\NoUnusedVariables;

class NoUnusedVariablesTest extends TestCase
{
    // Validate: No unused variables
    public function testUsesAllVariables()
    {
        $this->expectPassesRule(new NoUnusedVariables(), '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a, b: $b, c: $c)
      }
        ');
    }

    public function testUsesAllVariablesDeeply()
    {
        $this->expectPassesRule(new NoUnusedVariables, '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a) {
          field(b: $b) {
            field(c: $c)
          }
        }
      }
        ');
    }

    public function testUsesAllVariablesDeeplyInInlineFragments()
    {
        $this->expectPassesRule(new NoUnusedVariables, '
      query Foo($a: String, $b: String, $c: String) {
        ... on Type {
          field(a: $a) {
            field(b: $b) {
              ... on Type {
                field(c: $c)
              }
            }
          }
        }
      }
        ');
    }

    public function testUsesAllVariablesInFragments()
    {
        $this->expectPassesRule(new NoUnusedVariables, '
      query Foo($a: String, $b: String, $c: String) {
        ...FragA
      }
      fragment FragA on Type {
        field(a: $a) {
          ...FragB
        }
      }
      fragment FragB on Type {
        field(b: $b) {
          ...FragC
        }
      }
      fragment FragC on Type {
        field(c: $c)
      }
        ');
    }

    public function testVariableUsedByFragmentInMultipleOperations()
    {
        $this->expectPassesRule(new NoUnusedVariables, '
      query Foo($a: String) {
        ...FragA
      }
      query Bar($b: String) {
        ...FragB
      }
      fragment FragA on Type {
        field(a: $a)
      }
      fragment FragB on Type {
        field(b: $b)
      }
        ');
    }

    public function testVariableUsedByRecursiveFragment()
    {
        $this->expectPassesRule(new NoUnusedVariables, '
      query Foo($a: String) {
        ...FragA
      }
      fragment FragA on Type {
        field(a: $a) {
          ...FragA
        }
      }
        ');
    }

    public function testVariableNotUsed()
    {
        $this->expectFailsRule(new NoUnusedVariables, '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a, b: $b)
      }
        ', [
            $this->unusedVar('c', 2, 41)
        ]);
    }

    public function testMultipleVariablesNotUsed()
    {
        $this->expectFailsRule(new NoUnusedVariables, '
      query Foo($a: String, $b: String, $c: String) {
        field(b: $b)
      }
        ', [
            $this->unusedVar('a', 2, 17),
            $this->unusedVar('c', 2, 41)
        ]);
    }

    public function testVariableNotUsedInFragments()
    {
        $this->expectFailsRule(new NoUnusedVariables, '
      query Foo($a: String, $b: String, $c: String) {
        ...FragA
      }
      fragment FragA on Type {
        field(a: $a) {
          ...FragB
        }
      }
      fragment FragB on Type {
        field(b: $b) {
          ...FragC
        }
      }
      fragment FragC on Type {
        field
      }
        ', [
            $this->unusedVar('c', 2, 41)
        ]);
    }

    public function testMultipleVariablesNotUsed2()
    {
        $this->expectFailsRule(new NoUnusedVariables, '
      query Foo($a: String, $b: String, $c: String) {
        ...FragA
      }
      fragment FragA on Type {
        field {
          ...FragB
        }
      }
      fragment FragB on Type {
        field(b: $b) {
          ...FragC
        }
      }
      fragment FragC on Type {
        field
      }
        ', [
            $this->unusedVar('a', 2, 17),
            $this->unusedVar('c', 2, 41)
        ]);
    }

    public function testVariableNotUsedByUnreferencedFragment()
    {
        $this->expectFailsRule(new NoUnusedVariables, '
      query Foo($b: String) {
        ...FragA
      }
      fragment FragA on Type {
        field(a: $a)
      }
      fragment FragB on Type {
        field(b: $b)
      }
        ', [
            $this->unusedVar('b', 2, 17)
        ]);
    }

    public function testVariableNotUsedByFragmentUsedByOtherOperation()
    {
        $this->expectFailsRule(new NoUnusedVariables, '
      query Foo($b: String) {
        ...FragA
      }
      query Bar($a: String) {
        ...FragB
      }
      fragment FragA on Type {
        field(a: $a)
      }
      fragment FragB on Type {
        field(b: $b)
      }
        ', [
            $this->unusedVar('b', 2, 17),
            $this->unusedVar('a', 5, 17)
        ]);
    }

    private function unusedVar($varName, $line, $column)
    {
        return FormattedError::create(
            NoUnusedVariables::unusedVariableMessage($varName),
            [new SourceLocation($line, $column)]
        );
    }
}
