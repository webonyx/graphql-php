<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\NoUnusedVariables;

class NoUnusedVariablesTest extends TestCase
{
    // Validate: No unused variables

    /**
     * @it uses all variables
     */
    public function testUsesAllVariables()
    {
        $this->expectPassesRule(new NoUnusedVariables(), '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a, b: $b, c: $c)
      }
        ');
    }

    /**
     * @it uses all variables deeply
     */
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

    /**
     * @it uses all variables deeply in inline fragments
     */
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

    /**
     * @it uses all variables in fragments
     */
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

    /**
     * @it variable used by fragment in multiple operations
     */
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

    /**
     * @it variable used by recursive fragment
     */
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

    /**
     * @it variable not used
     */
    public function testVariableNotUsed()
    {
        $this->expectFailsRule(new NoUnusedVariables, '
      query ($a: String, $b: String, $c: String) {
        field(a: $a, b: $b)
      }
        ', [
            $this->unusedVar('c', null, 2, 38)
        ]);
    }

    /**
     * @it multiple variables not used
     */
    public function testMultipleVariablesNotUsed()
    {
        $this->expectFailsRule(new NoUnusedVariables, '
      query Foo($a: String, $b: String, $c: String) {
        field(b: $b)
      }
        ', [
            $this->unusedVar('a', 'Foo', 2, 17),
            $this->unusedVar('c', 'Foo', 2, 41)
        ]);
    }

    /**
     * @it variable not used in fragments
     */
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
            $this->unusedVar('c', 'Foo', 2, 41)
        ]);
    }

    /**
     * @it multiple variables not used
     */
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
            $this->unusedVar('a', 'Foo', 2, 17),
            $this->unusedVar('c', 'Foo', 2, 41)
        ]);
    }

    /**
     * @it variable not used by unreferenced fragment
     */
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
            $this->unusedVar('b', 'Foo', 2, 17)
        ]);
    }

    /**
     * @it variable not used by fragment used by other operation
     */
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
            $this->unusedVar('b', 'Foo', 2, 17),
            $this->unusedVar('a', 'Bar', 5, 17)
        ]);
    }

    private function unusedVar($varName, $opName, $line, $column)
    {
        return FormattedError::create(
            NoUnusedVariables::unusedVariableMessage($varName, $opName),
            [new SourceLocation($line, $column)]
        );
    }
}
