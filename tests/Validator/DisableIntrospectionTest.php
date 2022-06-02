<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\DisableIntrospection;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
class DisableIntrospectionTest extends ValidatorTestCase
{
    public function testFailsIfQueryContainsSchema(): void
    {
        $this->expectFailsRule(
            new DisableIntrospection(DisableIntrospection::ENABLED),
            '
      query { 
        __schema {
          queryType {
            name
          }
        }
      }
        ',
            [$this->error(3, 9)]
        );
    }

    /**
     * @phpstan-return ErrorArray
     */
    private function error(int $line, int $column): array
    {
        return ErrorHelper::create(
            DisableIntrospection::introspectionDisabledMessage(),
            [new SourceLocation($line, $column)]
        );
    }

    public function testFailsIfQueryContainsType(): void
    {
        $this->expectFailsRule(
            new DisableIntrospection(DisableIntrospection::ENABLED),
            '
      query { 
        __type(
          name: "Query"
        ){
          name
        }
      }
        ',
            [$this->error(3, 9)]
        );
    }

    public function testValidQuery(): void
    {
        $this->expectPassesRule(
            new DisableIntrospection(DisableIntrospection::ENABLED),
            '
      query {
        user {
          name
          email
          friends {
            name
          }
        }
      }
        '
        );
    }

    public function testAllowsIntrospectionWhenDisabled(): void
    {
        $this->expectPassesRule(
            new DisableIntrospection(DisableIntrospection::DISABLED),
            '
      query { 
        __type(
          name: "Query"
        ){
          name
        }
      }
        '
        );
    }

    public function testPublicEnableInterface(): void
    {
        $disableIntrospection = new DisableIntrospection(DisableIntrospection::DISABLED);
        $disableIntrospection->setEnabled(DisableIntrospection::ENABLED);
        $this->expectFailsRule(
            $disableIntrospection,
            '
      query { 
        __type(
          name: "Query"
        ){
          name
        }
      }
        ',
            [$this->error(3, 9)]
        );
    }

    public function testPublicDisableInterface(): void
    {
        $disableIntrospection = new DisableIntrospection(DisableIntrospection::ENABLED);
        $disableIntrospection->setEnabled(DisableIntrospection::DISABLED);
        $this->expectPassesRule(
            $disableIntrospection,
            '
      query { 
        __type(
          name: "Query"
        ){
          name
        }
      }
        '
        );
    }
}
