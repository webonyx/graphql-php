<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\DisableIntrospection;

class DisableIntrospectionTest extends TestCase
{
    // Validate: Disable Introspection

    /**
     * @it fails if the query contains __schema
     */
    public function testQueryContainsSchema()
    {
        $this->expectFailsRule(new DisableIntrospection(DisableIntrospection::ENABLED), '
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
     * @it fails if the query contains __type
     */
    public function testQueryContainsType()
    {
        $this->expectFailsRule(new DisableIntrospection(DisableIntrospection::ENABLED), '
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

    /**
     * @it does not fail on a query that does not contain __type
     */
    public function testValidQuery()
    {
        $this->expectPassesRule(new DisableIntrospection(DisableIntrospection::ENABLED), '
      query {
        user {
          name
          email
          friends {
            name
          }
        }
      }
        ');
    }

    /**
     * @it does not fail when not enabled
     */
    public function testQueryWhenDisabled()
    {
        $this->expectPassesRule(new DisableIntrospection(DisableIntrospection::DISABLED), '
      query { 
        __type(
          name: "Query"
        ){
          name
        }
      }
        ');
    }

    /**
     * @it has a public interface for enabeling the rule
     */
    public function testPublicEnableInterface()
    {
        $disableIntrospection = new DisableIntrospection(DisableIntrospection::DISABLED);
        $disableIntrospection->setEnabled(DisableIntrospection::ENABLED);
        $this->expectFailsRule($disableIntrospection, '
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

    /**
     * @it has a public interface for disableing the rule
     */
    public function testPublicDisableInterface()
    {
        $disableIntrospection = new DisableIntrospection(DisableIntrospection::ENABLED);
        $disableIntrospection->setEnabled(DisableIntrospection::DISABLED);
        $this->expectPassesRule($disableIntrospection, '
      query { 
        __type(
          name: "Query"
        ){
          name
        }
      }
        ');
    }


    private function error($line, $column)
    {
        return FormattedError::create(
            DisableIntrospection::introspectionDisabledMessage(),
            [ new SourceLocation($line, $column) ]
        );
    }
}
