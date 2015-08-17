<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\DefaultValuesOfCorrectType;

class DefaultValuesOfCorrectTypeTest extends TestCase
{
    // Validate: Variable default values of correct type

    public function testVariablesWithNoDefaultValues()
    {
        $this->expectPassesRule(new DefaultValuesOfCorrectType, '
      query NullableValues($a: Int, $b: String, $c: ComplexInput) {
        dog { name }
      }
        ');
    }

    public function testRequiredVariablesWithoutDefaultValues()
    {
        $this->expectPassesRule(new DefaultValuesOfCorrectType, '
      query RequiredValues($a: Int!, $b: String!) {
        dog { name }
      }
        ');
    }

    public function testVariablesWithValidDefaultValues()
    {
        $this->expectPassesRule(new DefaultValuesOfCorrectType, '
      query WithDefaultValues(
        $a: Int = 1,
        $b: String = "ok",
        $c: ComplexInput = { requiredField: true, intField: 3 }
      ) {
        dog { name }
      }
        ');
    }

    public function testNoRequiredVariablesWithDefaultValues()
    {
        $this->expectFailsRule(new DefaultValuesOfCorrectType, '
      query UnreachableDefaultValues($a: Int! = 3, $b: String! = "default") {
        dog { name }
      }
        ', [
            $this->defaultForNonNullArg('a', 'Int!', 'Int', 2, 49),
            $this->defaultForNonNullArg('b', 'String!', 'String', 2, 66)
        ]);
    }

    public function testVariablesWithInvalidDefaultValues()
    {
        $this->expectFailsRule(new DefaultValuesOfCorrectType, '
      query InvalidDefaultValues(
        $a: Int = "one",
        $b: String = 4,
        $c: ComplexInput = "notverycomplex"
      ) {
        dog { name }
      }
        ', [
            $this->badValue('a', 'Int', '"one"', 3, 19),
            $this->badValue('b', 'String', '4', 4, 22),
            $this->badValue('c', 'ComplexInput', '"notverycomplex"', 5, 28)
        ]);
    }

    public function testComplexVariablesMissingRequiredField()
    {
        $this->expectFailsRule(new DefaultValuesOfCorrectType, '
      query MissingRequiredField($a: ComplexInput = {intField: 3}) {
        dog { name }
      }
        ', [
            $this->badValue('a', 'ComplexInput', '{intField: 3}', 2, 53)
        ]);
    }

    public function testListVariablesWithInvalidItem()
    {
        $this->expectFailsRule(new DefaultValuesOfCorrectType, '
      query InvalidItem($a: [String] = ["one", 2]) {
        dog { name }
      }
        ', [
            $this->badValue('a', '[String]', '["one", 2]', 2, 40)
        ]);
    }

    private function defaultForNonNullArg($varName, $typeName, $guessTypeName, $line, $column)
    {
        return FormattedError::create(
            Messages::defaultForNonNullArgMessage($varName, $typeName, $guessTypeName),
            [ new SourceLocation($line, $column) ]
        );
    }

    private function badValue($varName, $typeName, $val, $line, $column)
    {
        return FormattedError::create(
            Messages::badValueForDefaultArgMessage($varName, $typeName, $val),
            [ new SourceLocation($line, $column) ]
        );
    }
}
