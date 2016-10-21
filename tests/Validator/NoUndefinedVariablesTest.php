<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\NoUndefinedVariables;

class NoUndefinedVariablesTest extends TestCase
{
    // Validate: No undefined variables

    /**
     * @it all variables defined
     */
    public function testAllVariablesDefined()
    {
        $this->expectPassesRule(new NoUndefinedVariables(), '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a, b: $b, c: $c)
      }
        ');
    }

    /**
     * @it all variables deeply defined
     */
    public function testAllVariablesDeeplyDefined()
    {
        $this->expectPassesRule(new NoUndefinedVariables, '
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
     * @it all variables deeply in inline fragments defined
     */
    public function testAllVariablesDeeplyInInlineFragmentsDefined()
    {
        $this->expectPassesRule(new NoUndefinedVariables, '
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
     * @it all variables in fragments deeply defined
     */
    public function testAllVariablesInFragmentsDeeplyDefined()
    {
        $this->expectPassesRule(new NoUndefinedVariables, '
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
     * @it variable within single fragment defined in multiple operations
     */
    public function testVariableWithinSingleFragmentDefinedInMultipleOperations()
    {
        // variable within single fragment defined in multiple operations
        $this->expectPassesRule(new NoUndefinedVariables, '
      query Foo($a: String) {
        ...FragA
      }
      query Bar($a: String) {
        ...FragA
      }
      fragment FragA on Type {
        field(a: $a)
      }
        ');
    }

    /**
     * @it variable within fragments defined in operations
     */
    public function testVariableWithinFragmentsDefinedInOperations()
    {
        $this->expectPassesRule(new NoUndefinedVariables, '
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
     * @it variable within recursive fragment defined
     */
    public function testVariableWithinRecursiveFragmentDefined()
    {
        $this->expectPassesRule(new NoUndefinedVariables, '
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
     * @it variable not defined
     */
    public function testVariableNotDefined()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a, b: $b, c: $c, d: $d)
      }
        ', [
            $this->undefVar('d', 3, 39, 'Foo', 2, 7)
        ]);
    }

    /**
     * @it variable not defined by un-named query
     */
    public function testVariableNotDefinedByUnNamedQuery()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
      {
        field(a: $a)
      }
        ', [
            $this->undefVar('a', 3, 18, '', 2, 7)
        ]);
    }

    /**
     * @it multiple variables not defined
     */
    public function testMultipleVariablesNotDefined()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
      query Foo($b: String) {
        field(a: $a, b: $b, c: $c)
      }
        ', [
            $this->undefVar('a', 3, 18, 'Foo', 2, 7),
            $this->undefVar('c', 3, 32, 'Foo', 2, 7)
        ]);
    }

    /**
     * @it variable in fragment not defined by un-named query
     */
    public function testVariableInFragmentNotDefinedByUnNamedQuery()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
      {
        ...FragA
      }
      fragment FragA on Type {
        field(a: $a)
      }
        ', [
            $this->undefVar('a', 6, 18, '', 2, 7)
        ]);
    }

    /**
     * @it variable in fragment not defined by operation
     */
    public function testVariableInFragmentNotDefinedByOperation()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
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
        ', [
            $this->undefVar('c', 16, 18, 'Foo', 2, 7)
        ]);
    }

    /**
     * @it multiple variables in fragments not defined
     */
    public function testMultipleVariablesInFragmentsNotDefined()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
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
        ', [
            $this->undefVar('a', 6, 18, 'Foo', 2, 7),
            $this->undefVar('c', 16, 18, 'Foo', 2, 7)
        ]);
    }

    /**
     * @it single variable in fragment not defined by multiple operations
     */
    public function testSingleVariableInFragmentNotDefinedByMultipleOperations()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
      query Foo($a: String) {
        ...FragAB
      }
      query Bar($a: String) {
        ...FragAB
      }
      fragment FragAB on Type {
        field(a: $a, b: $b)
      }
        ', [
            $this->undefVar('b', 9, 25, 'Foo', 2, 7),
            $this->undefVar('b', 9, 25, 'Bar', 5, 7)
        ]);
    }

    /**
     * @it variables in fragment not defined by multiple operations
     */
    public function testVariablesInFragmentNotDefinedByMultipleOperations()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
      query Foo($b: String) {
        ...FragAB
      }
      query Bar($a: String) {
        ...FragAB
      }
      fragment FragAB on Type {
        field(a: $a, b: $b)
      }
        ', [
            $this->undefVar('a', 9, 18, 'Foo', 2, 7),
            $this->undefVar('b', 9, 25, 'Bar', 5, 7)
        ]);
    }

    /**
     * @it variable in fragment used by other operation
     */
    public function testVariableInFragmentUsedByOtherOperation()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
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
            $this->undefVar('a', 9, 18, 'Foo', 2, 7),
            $this->undefVar('b', 12, 18, 'Bar', 5, 7)
        ]);
    }

    /**
     * @it multiple undefined variables produce multiple errors
     */
    public function testMultipleUndefinedVariablesProduceMultipleErrors()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
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
    ', [
            $this->undefVar('a', 9, 19, 'Foo', 2, 7),
            $this->undefVar('a', 11, 19, 'Foo', 2, 7),
            $this->undefVar('c', 14, 19, 'Foo', 2, 7),
            $this->undefVar('b', 9, 26, 'Bar', 5, 7),
            $this->undefVar('b', 11, 26, 'Bar', 5, 7),
            $this->undefVar('c', 14, 19, 'Bar', 5, 7),
        ]);
    }


    private function undefVar($varName, $line, $column, $opName = null, $l2 = null, $c2 = null)
    {
        $locs = [new SourceLocation($line, $column)];

        if ($l2 && $c2) {
            $locs[] = new SourceLocation($l2, $c2);
        }

        return FormattedError::create(
            NoUndefinedVariables::undefinedVarMessage($varName, $opName),
            $locs
        );
    }
}
