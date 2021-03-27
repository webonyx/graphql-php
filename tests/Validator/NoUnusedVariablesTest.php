<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\NoUnusedVariables;

class NoUnusedVariablesTest extends ValidatorTestCase
{
    // Validate: No unused variables

    /**
     * @see it('uses all variables')
     */
    public function testUsesAllVariables(): void
    {
        $this->expectPassesRule(
            new NoUnusedVariables(),
            '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a, b: $b, c: $c)
      }
        '
        );
    }

    /**
     * @see it('uses all variables deeply')
     */
    public function testUsesAllVariablesDeeply(): void
    {
        $this->expectPassesRule(
            new NoUnusedVariables(),
            '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a) {
          field(b: $b) {
            field(c: $c)
          }
        }
      }
        '
        );
    }

    /**
     * @see it('uses all variables deeply in inline fragments')
     */
    public function testUsesAllVariablesDeeplyInInlineFragments(): void
    {
        $this->expectPassesRule(
            new NoUnusedVariables(),
            '
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
        '
        );
    }

    /**
     * @see it('uses all variables in fragments')
     */
    public function testUsesAllVariablesInFragments(): void
    {
        $this->expectPassesRule(
            new NoUnusedVariables(),
            '
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
        '
        );
    }

    /**
     * @see it('variable used by fragment in multiple operations')
     */
    public function testVariableUsedByFragmentInMultipleOperations(): void
    {
        $this->expectPassesRule(
            new NoUnusedVariables(),
            '
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
        '
        );
    }

    /**
     * @see it('variable used by recursive fragment')
     */
    public function testVariableUsedByRecursiveFragment(): void
    {
        $this->expectPassesRule(
            new NoUnusedVariables(),
            '
      query Foo($a: String) {
        ...FragA
      }
      fragment FragA on Type {
        field(a: $a) {
          ...FragA
        }
      }
        '
        );
    }

    /**
     * @see it('variable not used')
     */
    public function testVariableNotUsed(): void
    {
        $this->expectFailsRule(
            new NoUnusedVariables(),
            '
      query ($a: String, $b: String, $c: String) {
        field(a: $a, b: $b)
      }
        ',
            [
                $this->unusedVar('c', null, 2, 38),
            ]
        );
    }

    private function unusedVar($varName, $opName, $line, $column)
    {
        return FormattedError::create(
            NoUnusedVariables::unusedVariableMessage($varName, $opName),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('multiple variables not used')
     */
    public function testMultipleVariablesNotUsed(): void
    {
        $this->expectFailsRule(
            new NoUnusedVariables(),
            '
      query Foo($a: String, $b: String, $c: String) {
        field(b: $b)
      }
        ',
            [
                $this->unusedVar('a', 'Foo', 2, 17),
                $this->unusedVar('c', 'Foo', 2, 41),
            ]
        );
    }

    /**
     * @see it('variable not used in fragments')
     */
    public function testVariableNotUsedInFragments(): void
    {
        $this->expectFailsRule(
            new NoUnusedVariables(),
            '
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
        ',
            [
                $this->unusedVar('c', 'Foo', 2, 41),
            ]
        );
    }

    /**
     * @see it('multiple variables not used')
     */
    public function testMultipleVariablesNotUsed2(): void
    {
        $this->expectFailsRule(
            new NoUnusedVariables(),
            '
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
        ',
            [
                $this->unusedVar('a', 'Foo', 2, 17),
                $this->unusedVar('c', 'Foo', 2, 41),
            ]
        );
    }

    /**
     * @see it('variable not used by unreferenced fragment')
     */
    public function testVariableNotUsedByUnreferencedFragment(): void
    {
        $this->expectFailsRule(
            new NoUnusedVariables(),
            '
      query Foo($b: String) {
        ...FragA
      }
      fragment FragA on Type {
        field(a: $a)
      }
      fragment FragB on Type {
        field(b: $b)
      }
        ',
            [
                $this->unusedVar('b', 'Foo', 2, 17),
            ]
        );
    }

    /**
     * @see it('variable not used by fragment used by other operation')
     */
    public function testVariableNotUsedByFragmentUsedByOtherOperation(): void
    {
        $this->expectFailsRule(
            new NoUnusedVariables(),
            '
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
        ',
            [
                $this->unusedVar('b', 'Foo', 2, 17),
                $this->unusedVar('a', 'Bar', 5, 17),
            ]
        );
    }
}
