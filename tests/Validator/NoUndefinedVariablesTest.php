<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\NoUndefinedVariables;

class NoUndefinedVariablesTest extends TestCase
{
    // Validate: No undefined variables

    public function testAllVariablesDefined()
    {
        $this->expectPassesRule(new NoUndefinedVariables(), '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a, b: $b, c: $c)
      }
        ');
    }

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

    public function testVariableNotDefined()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
      query Foo($a: String, $b: String, $c: String) {
        field(a: $a, b: $b, c: $c, d: $d)
      }
        ', [
            $this->undefVar('d', 3, 39)
        ]);
    }

    public function testVariableNotDefinedByUnNamedQuery()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
      {
        field(a: $a)
      }
        ', [
            $this->undefVar('a', 3, 18)
        ]);
    }

    public function testMultipleVariablesNotDefined()
    {
        $this->expectFailsRule(new NoUndefinedVariables, '
      query Foo($b: String) {
        field(a: $a, b: $b, c: $c)
      }
        ', [
            $this->undefVar('a', 3, 18),
            $this->undefVar('c', 3, 32)
        ]);
    }

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
            $this->undefVar('a', 6, 18)
        ]);
    }

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
            $this->undefVarByOp('c', 16, 18, 'Foo', 2, 7)
        ]);
    }

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
            $this->undefVarByOp('a', 6, 18, 'Foo', 2, 7),
            $this->undefVarByOp('c', 16, 18, 'Foo', 2, 7)
        ]);
    }

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
            $this->undefVarByOp('b', 9, 25, 'Foo', 2, 7),
            $this->undefVarByOp('b', 9, 25, 'Bar', 5, 7)
        ]);
    }

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
            $this->undefVarByOp('a', 9, 18, 'Foo', 2, 7),
            $this->undefVarByOp('b', 9, 25, 'Bar', 5, 7)
        ]);
    }

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
            $this->undefVarByOp('a', 9, 18, 'Foo', 2, 7),
            $this->undefVarByOp('b', 12, 18, 'Bar', 5, 7)
        ]);
    }

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
            $this->undefVarByOp('a', 9, 19, 'Foo', 2, 7),
            $this->undefVarByOp('c', 14, 19, 'Foo', 2, 7),
            $this->undefVarByOp('a', 11, 19, 'Foo', 2, 7),
            $this->undefVarByOp('b', 9, 26, 'Bar', 5, 7),
            $this->undefVarByOp('c', 14, 19, 'Bar', 5, 7),
            $this->undefVarByOp('b', 11, 26, 'Bar', 5, 7),
        ]);
    }


    private function undefVar($varName, $line, $column)
    {
        return FormattedError::create(
            NoUndefinedVariables::undefinedVarMessage($varName),
            [new SourceLocation($line, $column)]
        );
    }

    private function undefVarByOp($varName, $l1, $c1, $opName, $l2, $c2)
    {
        return FormattedError::create(
            NoUndefinedVariables::undefinedVarByOpMessage($varName, $opName),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
