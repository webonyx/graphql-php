<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\VariablesDefaultValueAllowed;

class VariablesDefaultValueAllowedTest extends ValidatorTestCase
{
    private function defaultForRequiredVar($varName, $typeName, $guessTypeName, $line, $column)
    {
        return FormattedError::create(
            VariablesDefaultValueAllowed::defaultForRequiredVarMessage(
                $varName,
                $typeName,
                $guessTypeName
            ),
            [new SourceLocation($line, $column)]
        );
    }

    // DESCRIBE: Validate: Variable default value is allowed

    /**
     * @see it('variables with no default values')
     */
    public function testVariablesWithNoDefaultValues() : void
    {
        $this->expectPassesRule(new VariablesDefaultValueAllowed(), '
      query NullableValues($a: Int, $b: String, $c: ComplexInput) {
        dog { name }
      }
        ');
    }

    /**
     * @see it('required variables without default values')
     */
    public function testRequiredVariablesWithoutDefaultValues() : void
    {
        $this->expectPassesRule(new VariablesDefaultValueAllowed(), '
      query RequiredValues($a: Int!, $b: String!) {
        dog { name }
      }
        ');
    }

    /**
     * @see it('variables with valid default values')
     */
    public function testVariablesWithValidDefaultValues() : void
    {
        $this->expectPassesRule(new VariablesDefaultValueAllowed(), '
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
     * @see it('variables with valid default null values')
     */
    public function testVariablesWithValidDefaultNullValues() : void
    {
        $this->expectPassesRule(new VariablesDefaultValueAllowed(), '
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
     * @see it('no required variables with default values')
     */
    public function testNoRequiredVariablesWithDefaultValues() : void
    {
        $this->expectFailsRule(new VariablesDefaultValueAllowed(), '
      query UnreachableDefaultValues($a: Int! = 3, $b: String! = "default") {
        dog { name }
      }
        ', [
            $this->defaultForRequiredVar('a', 'Int!', 'Int', 2, 49),
            $this->defaultForRequiredVar('b', 'String!', 'String', 2, 66),
        ]);
    }

    /**
     * @see it('variables with invalid default null values')
     */
    public function testNullIntoNullableType() : void
    {
        $this->expectFailsRule(new VariablesDefaultValueAllowed(), '
      query WithDefaultValues($a: Int! = null, $b: String! = null) {
        dog { name }
      }
        ', [
            $this->defaultForRequiredVar('a', 'Int!', 'Int', 2, 42),
            $this->defaultForRequiredVar('b', 'String!', 'String', 2, 62),
        ]);
    }
}
