<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\DefaultValuesOfCorrectType;

class DefaultValuesOfCorrectTypeTest extends TestCase
{
    // Validate: Variable default values of correct type

    /**
     * @it variables with no default values
     */
    public function testVariablesWithNoDefaultValues()
    {
        $this->expectPassesRule(new DefaultValuesOfCorrectType, '
      query NullableValues($a: Int, $b: String, $c: ComplexInput) {
        dog { name }
      }
        ');
    }

    /**
     * @it required variables without default values
     */
    public function testRequiredVariablesWithoutDefaultValues()
    {
        $this->expectPassesRule(new DefaultValuesOfCorrectType, '
      query RequiredValues($a: Int!, $b: String!) {
        dog { name }
      }
        ');
    }

    /**
     * @it variables with valid default values
     */
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

    /**
     * @it variables with valid default null values
     */
    public function testVariablesWithValidDefaultNullValues()
    {
        $this->expectPassesRule(new DefaultValuesOfCorrectType(), '
      query WithDefaultValues(
        $a: Int = null,
        $b: String = null,
        $c: ComplexInput = { requiredField: true, intField: null }
      ) {
        dog { name }
      }
        ');
    }

    /**
     * @it variables with invalid default null values
     */
    public function testVariablesWithInvalidDefaultNullValues()
    {
        $this->expectFailsRule(new DefaultValuesOfCorrectType(), '
      query WithDefaultValues(
        $a: Int! = null,
        $b: String! = null,
        $c: ComplexInput = { requiredField: null, intField: null }
      ) {
        dog { name }
      }
        ', [
            $this->defaultForNonNullArg('a', 'Int!', 'Int', 3, 20),
            $this->badValue('a', 'Int!', 'null', 3, 20, [
                'Expected "Int!", found null.'
            ]),
            $this->defaultForNonNullArg('b', 'String!', 'String', 4, 23),
            $this->badValue('b', 'String!', 'null', 4, 23, [
                'Expected "String!", found null.'
            ]),
            $this->badValue('c', 'ComplexInput', '{requiredField: null, intField: null}',
                5, 28, [
                    'In field "requiredField": Expected "Boolean!", found null.'
                ]
            ),
        ]);
    }

    /**
     * @it no required variables with default values
     */
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

    /**
     * @it variables with invalid default values
     */
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
            $this->badValue('a', 'Int', '"one"', 3, 19, [
                'Expected type "Int", found "one".'
            ]),
            $this->badValue('b', 'String', '4', 4, 22, [
                'Expected type "String", found 4.'
            ]),
            $this->badValue('c', 'ComplexInput', '"notverycomplex"', 5, 28, [
                'Expected "ComplexInput", found not an object.'
            ])
        ]);
    }

    /**
     * @it complex variables missing required field
     */
    public function testComplexVariablesMissingRequiredField()
    {
        $this->expectFailsRule(new DefaultValuesOfCorrectType, '
      query MissingRequiredField($a: ComplexInput = {intField: 3}) {
        dog { name }
      }
        ', [
            $this->badValue('a', 'ComplexInput', '{intField: 3}', 2, 53, [
                'In field "requiredField": Expected "Boolean!", found null.'
            ])
        ]);
    }

    /**
     * @it list variables with invalid item
     */
    public function testListVariablesWithInvalidItem()
    {
        $this->expectFailsRule(new DefaultValuesOfCorrectType, '
      query InvalidItem($a: [String] = ["one", 2]) {
        dog { name }
      }
        ', [
            $this->badValue('a', '[String]', '["one", 2]', 2, 40, [
                'In element #1: Expected type "String", found 2.'
            ])
        ]);
    }

    private function defaultForNonNullArg($varName, $typeName, $guessTypeName, $line, $column)
    {
        return FormattedError::create(
            DefaultValuesOfCorrectType::defaultForNonNullArgMessage($varName, $typeName, $guessTypeName),
            [ new SourceLocation($line, $column) ]
        );
    }

    private function badValue($varName, $typeName, $val, $line, $column, $errors = null)
    {
        $realErrors = !$errors ? ["Expected type \"$typeName\", found $val."] : $errors;

        return FormattedError::create(
            DefaultValuesOfCorrectType::badValueForDefaultArgMessage($varName, $typeName, $val, $realErrors),
            [ new SourceLocation($line, $column) ]
        );
    }
}
