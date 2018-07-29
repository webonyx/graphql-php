<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\ExecutableDefinitions;
use GraphQL\Validator\Rules\KnownDirectives;

class ExecutableDefinitionsTest extends ValidatorTestCase
{
    // Validate: Executable definitions

    /**
     * @it with only operation
     */
    public function testWithOnlyOperation()
    {
        $this->expectPassesRule(new ExecutableDefinitions, '
      query Foo {
        dog {
          name
        }
      }
        ');
    }

    /**
     * @it with operation and fragment
     */
    public function testWithOperationAndFragment()
    {
        $this->expectPassesRule(new ExecutableDefinitions, '
      query Foo {
        dog {
          name
          ...Frag
        }
      }
      
      fragment Frag on Dog {
        name
      }
        ');
    }

    /**
     * @it with typeDefinition
     */
    public function testWithTypeDefinition()
    {
        $this->expectFailsRule(new ExecutableDefinitions, '
      query Foo {
        dog {
          name
        }
      }
      
      type Cow {
        name: String
      }
      
      extend type Dog {
        color: String
      }
        ',
            [
                $this->nonExecutableDefinition('Cow', 8, 12),
                $this->nonExecutableDefinition('Dog', 12, 19),
            ]);
    }

    private function nonExecutableDefinition($defName, $line, $column)
    {
        return FormattedError::create(
            ExecutableDefinitions::nonExecutableDefinitionMessage($defName),
            [ new SourceLocation($line, $column) ]
        );
    }
}
