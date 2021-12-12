<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\ExecutableDefinitions;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
class ExecutableDefinitionsTest extends ValidatorTestCase
{
    // Validate: Executable definitions

    /**
     * @see it('with only operation')
     */
    public function testWithOnlyOperation(): void
    {
        $this->expectPassesRule(
            new ExecutableDefinitions(),
            '
      query Foo {
        dog {
          name
        }
      }
        '
        );
    }

    /**
     * @see it('with operation and fragment')
     */
    public function testWithOperationAndFragment(): void
    {
        $this->expectPassesRule(
            new ExecutableDefinitions(),
            '
      query Foo {
        dog {
          name
          ...Frag
        }
      }
      
      fragment Frag on Dog {
        name
      }
        '
        );
    }

    /**
     * @see it('with typeDefinition')
     */
    public function testWithTypeDefinition(): void
    {
        $this->expectFailsRule(
            new ExecutableDefinitions(),
            '
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
            ]
        );
    }

    /**
     * @phpstan-return ErrorArray
     */

    private function nonExecutableDefinition(string $defName, int $line, int $column): array
    {
        return ErrorHelper::create(
            ExecutableDefinitions::nonExecutableDefinitionMessage($defName),
            [new SourceLocation($line, $column)]
        );
    }
}
