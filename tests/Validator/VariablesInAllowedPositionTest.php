<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\VariablesInAllowedPosition;

class VariablesInAllowedPositionTest extends TestCase
{
    // Validate: Variables are in allowed positions

    /**
     * @it Boolean => Boolean
     */
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

    /**
     * @it Boolean => Boolean within fragment
     */
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

    /**
     * @it Boolean! => Boolean
     */
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

    /**
     * @it Boolean! => Boolean within fragment
     */
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

    /**
     * @it Int => Int! with default
     */
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

    /**
     * @it [String] => [String]
     */
    public function testListOfStringXListOfString()
    {
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($stringListVar: [String])
      {
        complicatedArgs {
          stringListArgField(stringListArg: $stringListVar)
        }
      }
        ');
    }

    /**
     * @it [String!] => [String]
     */
    public function testListOfStringNonNullXListOfString()
    {
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($stringListVar: [String!])
      {
        complicatedArgs {
          stringListArgField(stringListArg: $stringListVar)
        }
      }
        ');
    }

    /**
     * @it String => [String] in item position
     */
    public function testStringXListOfStringInItemPosition()
    {
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($stringVar: String)
      {
        complicatedArgs {
          stringListArgField(stringListArg: [$stringVar])
        }
      }
        ');
    }

    /**
     * @it String! => [String] in item position
     */
    public function testStringNonNullXListOfStringInItemPosition()
    {
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($stringVar: String!)
      {
        complicatedArgs {
          stringListArgField(stringListArg: [$stringVar])
        }
      }
        ');
    }

    /**
     * @it ComplexInput => ComplexInput
     */
    public function testComplexInputXComplexInput()
    {
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($complexVar: ComplexInput)
      {
        complicatedArgs {
          complexArgField(complexArg: $ComplexInput)
        }
      }
        ');
    }

    /**
     * @it ComplexInput => ComplexInput in field position
     */
    public function testComplexInputXComplexInputInFieldPosition()
    {
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($boolVar: Boolean = false)
      {
        complicatedArgs {
          complexArgField(complexArg: {requiredArg: $boolVar})
        }
      }
        ');
    }

    /**
     * @it Boolean! => Boolean! in directive
     */
    public function testBooleanNonNullXBooleanNonNullInDirective()
    {
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($boolVar: Boolean!)
      {
        dog @include(if: $boolVar)
      }
        ');
    }

    /**
     * @it Boolean => Boolean! in directive with default
     */
    public function testBooleanXBooleanNonNullInDirectiveWithDefault()
    {
        $this->expectPassesRule(new VariablesInAllowedPosition, '
      query Query($boolVar: Boolean = false)
      {
        dog @include(if: $boolVar)
      }
        ');
    }

    /**
     * @it Int => Int!
     */
    public function testIntXIntNonNull()
    {
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      query Query($intArg: Int) {
        complicatedArgs {
          nonNullIntArgField(nonNullIntArg: $intArg)
        }
      }
        ', [
            FormattedError::create(
                VariablesInAllowedPosition::badVarPosMessage('intArg', 'Int', 'Int!'),
                [new SourceLocation(2, 19), new SourceLocation(4, 45)]
            )
        ]);
    }

    /**
     * @it Int => Int! within fragment
     */
    public function testIntXIntNonNullWithinFragment()
    {
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      fragment nonNullIntArgFieldFrag on ComplicatedArgs {
        nonNullIntArgField(nonNullIntArg: $intArg)
      }

      query Query($intArg: Int) {
        complicatedArgs {
          ...nonNullIntArgFieldFrag
        }
      }
        ', [
            FormattedError::create(
                VariablesInAllowedPosition::badVarPosMessage('intArg', 'Int', 'Int!'),
                [new SourceLocation(6, 19), new SourceLocation(3, 43)]
            )
        ]);
    }

    /**
     * @it Int => Int! within nested fragment
     */
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
            FormattedError::create(
                VariablesInAllowedPosition::badVarPosMessage('intArg', 'Int', 'Int!'),
                [new SourceLocation(10, 19), new SourceLocation(7,43)]
            )
        ]);
    }

    /**
     * @it String over Boolean
     */
    public function testStringOverBoolean()
    {
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      query Query($stringVar: String) {
        complicatedArgs {
          booleanArgField(booleanArg: $stringVar)
        }
      }
        ', [
            FormattedError::create(
                VariablesInAllowedPosition::badVarPosMessage('stringVar', 'String', 'Boolean'),
                [new SourceLocation(2,19), new SourceLocation(4,39)]
            )
        ]);
    }

    /**
     * @it String => [String]
     */
    public function testStringXListOfString()
    {
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      query Query($stringVar: String) {
        complicatedArgs {
          stringListArgField(stringListArg: $stringVar)
        }
      }
        ', [
            FormattedError::create(
                VariablesInAllowedPosition::badVarPosMessage('stringVar', 'String', '[String]'),
                [new SourceLocation(2, 19), new SourceLocation(4,45)]
            )
        ]);
    }

    /**
     * @it Boolean => Boolean! in directive
     */
    public function testBooleanXBooleanNonNullInDirective()
    {
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      query Query($boolVar: Boolean) {
        dog @include(if: $boolVar)
      }
        ', [
            FormattedError::create(
                VariablesInAllowedPosition::badVarPosMessage('boolVar', 'Boolean', 'Boolean!'),
                [new SourceLocation(2, 19), new SourceLocation(3,26)]
            )
        ]);
    }

    /**
     * @it String => Boolean! in directive
     */
    public function testStringXBooleanNonNullInDirective()
    {
        // String => Boolean! in directive
        $this->expectFailsRule(new VariablesInAllowedPosition, '
      query Query($stringVar: String) {
        dog @include(if: $stringVar)
      }
        ', [
            FormattedError::create(
                VariablesInAllowedPosition::badVarPosMessage('stringVar', 'String', 'Boolean!'),
                [new SourceLocation(2, 19), new SourceLocation(3,26)]
            )
        ]);
    }

}
