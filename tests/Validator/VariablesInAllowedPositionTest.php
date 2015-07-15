<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\VariablesInAllowedPosition;

class VariablesInAllowedPositionTest extends TestCase
{
    // Validate: Variables are in allowed positions

    public function testBooleanXBoolean()
    {
        // Boolean => Boolean
        $this->expectPassesRule(new VariablesInAllowedPosition(), '
      query Query($booleanArg: Boolean)
      {
        complicatedArgs {
          booleanArgField(booleanArg: $booleanArg)
        }
      }
        ');
    }

    public function testBooleanXBooleanWithinFragment()
    {
        // Boolean => Boolean within fragment
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      fragment booleanArgFrag on ComplicatedArgs {
        booleanArgField(booleanArg: $booleanArg)
      }
      query Query($booleanArg: Boolean)
      {
        complicatedArgs {
          ...booleanArgFrag
        }
      }
        ');

        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($booleanArg: Boolean)
      {
        complicatedArgs {
          ...booleanArgFrag
        }
      }
      fragment booleanArgFrag on ComplicatedArgs {
        booleanArgField(booleanArg: $booleanArg)
      }
        ');
    }

    public function testBooleanNonNullXBoolean()
    {
        // Boolean! => Boolean
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($nonNullBooleanArg: Boolean!)
      {
        complicatedArgs {
          booleanArgField(booleanArg: $nonNullBooleanArg)
        }
      }
        ');
    }

    public function testBooleanNonNullXBooleanWithinFragment()
    {
        // Boolean! => Boolean within fragment
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      fragment booleanArgFrag on ComplicatedArgs {
        booleanArgField(booleanArg: $nonNullBooleanArg)
      }

      query Query($nonNullBooleanArg: Boolean!)
      {
        complicatedArgs {
          ...booleanArgFrag
        }
      }
        ');
    }

    public function testIntXIntNonNullWithDefault()
    {
        // Int => Int! with default
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($intArg: Int = 1)
      {
        complicatedArgs {
          nonNullIntArgField(nonNullIntArg: $intArg)
        }
      }
        ');
    }

    public function testListOfStringXListOfString()
    {
        // [String] => [String]
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($stringListVar: [String])
      {
        complicatedArgs {
          stringListArgField(stringListArg: $stringListVar)
        }
      }
        ');
    }

    public function testListOfStringNonNullXListOfString()
    {
        // [String!] => [String]
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($stringListVar: [String!])
      {
        complicatedArgs {
          stringListArgField(stringListArg: $stringListVar)
        }
      }
        ');
    }

    public function testStringXListOfStringInItemPosition()
    {
        // String => [String] in item position
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($stringVar: String)
      {
        complicatedArgs {
          stringListArgField(stringListArg: [$stringVar])
        }
      }
        ');
    }

    public function testStringNonNullXListOfStringInItemPosition()
    {
        // String! => [String] in item position
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($stringVar: String!)
      {
        complicatedArgs {
          stringListArgField(stringListArg: [$stringVar])
        }
      }
        ');
    }

    public function testComplexInputXComplexInput()
    {
        // ComplexInput => ComplexInput
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($complexVar: ComplexInput)
      {
        complicatedArgs {
          complexArgField(complexArg: $ComplexInput)
        }
      }
        ');
    }

    public function testComplexInputXComplexInputInFieldPosition()
    {
        // ComplexInput => ComplexInput in field position
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($boolVar: Boolean = false)
      {
        complicatedArgs {
          complexArgField(complexArg: {requiredArg: $boolVar})
        }
      }
        ');
    }

    public function testBooleanNonNullXBooleanNonNullInDirective()
    {
        // Boolean! => Boolean! in directive
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($boolVar: Boolean!)
      {
        dog @if: $boolVar
      }
        ');
    }

    public function testBooleanXBooleanNonNullInDirectiveWithDefault()
    {
        // Boolean => Boolean! in directive with default
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($boolVar: Boolean = false)
      {
        dog @if: $boolVar
      }
        ');
    }

    public function testIntXIntNonNull()
    {
        // Int => Int!
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      query Query($intArg: Int)
      {
        complicatedArgs {
          nonNullIntArgField(nonNullIntArg: $intArg)
        }
      }
        ', [
            new FormattedError(
                Messages::badVarPosMessage('intArg', 'Int', 'Int!'),
                [new SourceLocation(5, 45)]
            )
        ]);
    }

    public function testIntXIntNonNullWithinFragment()
    {
        // Int => Int! within fragment
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      fragment nonNullIntArgFieldFrag on ComplicatedArgs {
        nonNullIntArgField(nonNullIntArg: $intArg)
      }

      query Query($intArg: Int)
      {
        complicatedArgs {
          ...nonNullIntArgFieldFrag
        }
      }
        ', [
            new FormattedError(
                Messages::badVarPosMessage('intArg', 'Int', 'Int!'),
                [new SourceLocation(3, 43)]
            )
        ]);
    }

    public function testIntXIntNonNullWithinNestedFragment()
    {
        // Int => Int! within nested fragment
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      fragment outerFrag on ComplicatedArgs {
        ...nonNullIntArgFieldFrag
      }

      fragment nonNullIntArgFieldFrag on ComplicatedArgs {
        nonNullIntArgField(nonNullIntArg: $intArg)
      }

      query Query($intArg: Int)
      {
        complicatedArgs {
          ...outerFrag
        }
      }
        ', [
            new FormattedError(
                Messages::badVarPosMessage('intArg', 'Int', 'Int!'),
                [new SourceLocation(7,43)]
            )
        ]);
    }

    public function testStringOverBoolean()
    {
        // String over Boolean
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      query Query($stringVar: String)
      {
        complicatedArgs {
          booleanArgField(booleanArg: $stringVar)
        }
      }
        ', [
            new FormattedError(
                Messages::badVarPosMessage('stringVar', 'String', 'Boolean'),
                [new SourceLocation(5,39)]
            )
        ]);
    }

    public function testStringXListOfString()
    {
        // String => [String]
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      query Query($stringVar: String)
      {
        complicatedArgs {
          stringListArgField(stringListArg: $stringVar)
        }
      }
        ', [
            new FormattedError(
                Messages::badVarPosMessage('stringVar', 'String', '[String]'),
                [new SourceLocation(5,45)]
            )
        ]);
    }

    public function testBooleanXBooleanNonNullInDirective()
    {
        // Boolean => Boolean! in directive
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      query Query($boolVar: Boolean)
      {
        dog @if: $boolVar
      }
        ', [
            new FormattedError(
                Messages::badVarPosMessage('boolVar', 'Boolean', 'Boolean!'),
                [new SourceLocation(4,18)]
            )
        ]);
    }

    public function testStringXBooleanNonNullInDirective()
    {
        // String => Boolean! in directive
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      query Query($stringVar: String)
      {
        dog @if: $stringVar
      }
        ', [
            new FormattedError(
                Messages::badVarPosMessage('stringVar', 'String', 'Boolean!'),
                [new SourceLocation(4,18)]
            )
        ]);
    }

}
