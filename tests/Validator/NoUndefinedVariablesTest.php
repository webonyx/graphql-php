<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\NoUndefinedVariables;

final class NoUndefinedVariablesTest extends ValidatorTestCase
{
    // Validate: No undefined variables

    /**
     * @see it('all variables defined')
     */
    public function testAllVariablesDefined(): void
    {
        $this->expectPassesRule(
            new NoUndefinedVariables(),
            '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a, b: $b, c: $c)
      }
        '
        );
    }

    /**
     * @see it('all variables deeply defined')
     */
    public function testAllVariablesDeeplyDefined(): void
    {
        $this->expectPassesRule(
            new NoUndefinedVariables(),
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
     * @see it('all variables deeply in inline fragments defined')
     */
    public function testAllVariablesDeeplyInInlineFragmentsDefined(): void
    {
        $this->expectPassesRule(
            new NoUndefinedVariables(),
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
     * @see it('all variables in fragments deeply defined')
     */
    public function testAllVariablesInFragmentsDeeplyDefined(): void
    {
        $this->expectPassesRule(
            new NoUndefinedVariables(),
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
     * @see it('variable within single fragment defined in multiple operations')
     */
    public function testVariableWithinSingleFragmentDefinedInMultipleOperations(): void
    {
        // variable within single fragment defined in multiple operations
        $this->expectPassesRule(
            new NoUndefinedVariables(),
            '
      query Foo($a: String) {
        ...FragA
      }
      query Bar($a: String) {
        ...FragA
      }
      fragment FragA on Type {
        field(a: $a)
      }
        '
        );
    }

    /**
     * @see it('variable within fragments defined in operations')
     */
    public function testVariableWithinFragmentsDefinedInOperations(): void
    {
        $this->expectPassesRule(
            new NoUndefinedVariables(),
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
     * @see it('variable within recursive fragment defined')
     */
    public function testVariableWithinRecursiveFragmentDefined(): void
    {
        $this->expectPassesRule(
            new NoUndefinedVariables(),
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
     * @see it('variable not defined')
     */
    public function testVariableNotDefined(): void
    {
        $this->expectFailsRule(
            new NoUndefinedVariables(),
            '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a, b: $b, c: $c, d: $d)
      }
        ',
            [
                $this->undefVar('d', 3, 39, 'Foo', 2, 7),
            ]
        );
    }

    /**
     * @return array{
     *     message: string,
     *     locations?: array<int, array{line: int, column: int}>
     * }
     */
    private function undefVar(string $varName, int $line, int $column, ?string $opName = null, ?int $l2 = null, ?int $c2 = null): array
    {
        $locs = [new SourceLocation($line, $column)];

        if ($l2 !== null && $c2 !== null) {
            $locs[] = new SourceLocation($l2, $c2);
        }

        return ErrorHelper::create(
            NoUndefinedVariables::undefinedVarMessage($varName, $opName),
            $locs
        );
    }

    /**
     * @see it('variable not defined by un-named query')
     */
    public function testVariableNotDefinedByUnNamedQuery(): void
    {
        $this->expectFailsRule(
            new NoUndefinedVariables(),
            '
      {
        field(a: $a)
      }
        ',
            [
                $this->undefVar('a', 3, 18, null, 2, 7),
            ]
        );
    }

    /**
     * @see it('multiple variables not defined')
     */
    public function testMultipleVariablesNotDefined(): void
    {
        $this->expectFailsRule(
            new NoUndefinedVariables(),
            '
      query Foo($b: String) {
        field(a: $a, b: $b, c: $c)
      }
        ',
            [
                $this->undefVar('a', 3, 18, 'Foo', 2, 7),
                $this->undefVar('c', 3, 32, 'Foo', 2, 7),
            ]
        );
    }

    /**
     * @see it('variable in fragment not defined by un-named query')
     */
    public function testVariableInFragmentNotDefinedByUnNamedQuery(): void
    {
        $this->expectFailsRule(
            new NoUndefinedVariables(),
            '
      {
        ...FragA
      }
      fragment FragA on Type {
        field(a: $a)
      }
        ',
            [
                $this->undefVar('a', 6, 18, null, 2, 7),
            ]
        );
    }

    /**
     * @see it('variable in fragment not defined by operation')
     */
    public function testVariableInFragmentNotDefinedByOperation(): void
    {
        $this->expectFailsRule(
            new NoUndefinedVariables(),
            '
      query Foo($a: String, $b: String) {
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
        ',
            [
                $this->undefVar('c', 16, 18, 'Foo', 2, 7),
            ]
        );
    }

    /**
     * @see it('multiple variables in fragments not defined')
     */
    public function testMultipleVariablesInFragmentsNotDefined(): void
    {
        $this->expectFailsRule(
            new NoUndefinedVariables(),
            '
      query Foo($b: String) {
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
        ',
            [
                $this->undefVar('a', 6, 18, 'Foo', 2, 7),
                $this->undefVar('c', 16, 18, 'Foo', 2, 7),
            ]
        );
    }

    /**
     * @see it('single variable in fragment not defined by multiple operations')
     */
    public function testSingleVariableInFragmentNotDefinedByMultipleOperations(): void
    {
        $this->expectFailsRule(
            new NoUndefinedVariables(),
            '
      query Foo($a: String) {
        ...FragAB
      }
      query Bar($a: String) {
        ...FragAB
      }
      fragment FragAB on Type {
        field(a: $a, b: $b)
      }
        ',
            [
                $this->undefVar('b', 9, 25, 'Foo', 2, 7),
                $this->undefVar('b', 9, 25, 'Bar', 5, 7),
            ]
        );
    }

    /**
     * @see it('variables in fragment not defined by multiple operations')
     */
    public function testVariablesInFragmentNotDefinedByMultipleOperations(): void
    {
        $this->expectFailsRule(
            new NoUndefinedVariables(),
            '
      query Foo($b: String) {
        ...FragAB
      }
      query Bar($a: String) {
        ...FragAB
      }
      fragment FragAB on Type {
        field(a: $a, b: $b)
      }
        ',
            [
                $this->undefVar('a', 9, 18, 'Foo', 2, 7),
                $this->undefVar('b', 9, 25, 'Bar', 5, 7),
            ]
        );
    }

    /**
     * @see it('variable in fragment used by other operation')
     */
    public function testVariableInFragmentUsedByOtherOperation(): void
    {
        $this->expectFailsRule(
            new NoUndefinedVariables(),
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
                $this->undefVar('a', 9, 18, 'Foo', 2, 7),
                $this->undefVar('b', 12, 18, 'Bar', 5, 7),
            ]
        );
    }

    /**
     * @see it('multiple undefined variables produce multiple errors')
     */
    public function testMultipleUndefinedVariablesProduceMultipleErrors(): void
    {
        $this->expectFailsRule(
            new NoUndefinedVariables(),
            '
      query Foo($b: String) {
        ...FragAB
      }
      query Bar($a: String) {
        ...FragAB
      }
      fragment FragAB on Type {
        field1(a: $a, b: $b)
        ...FragC
        field3(a: $a, b: $b)
      }
      fragment FragC on Type {
        field2(c: $c)
      }
    ',
            [
                $this->undefVar('a', 9, 19, 'Foo', 2, 7),
                $this->undefVar('a', 11, 19, 'Foo', 2, 7),
                $this->undefVar('c', 14, 19, 'Foo', 2, 7),
                $this->undefVar('b', 9, 26, 'Bar', 5, 7),
                $this->undefVar('b', 11, 26, 'Bar', 5, 7),
                $this->undefVar('c', 14, 19, 'Bar', 5, 7),
            ]
        );
    }
}
