<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Type\Introspection;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\AbstractQuerySecurity;

abstract class AbstractQuerySecurityTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param $max
     *
     * @return AbstractQuerySecurity
     */
    abstract protected function getRule($max);

    /**
     * @param $max
     * @param $count
     *
     * @return string
     */
    abstract protected function getErrorMessage($max, $count);

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage argument must be greater or equal to 0.
     */
    public function testMaxQueryDepthMustBeGreaterOrEqualTo0()
    {
        $this->getRule(-1);
    }

    protected function createFormattedError($max, $count, $locations = [])
    {
        return FormattedError::create($this->getErrorMessage($max, $count), $locations);
    }

    protected function assertDocumentValidator($queryString, $max, array $expectedErrors = [])
    {
        $errors = DocumentValidator::validate(
            QuerySecuritySchema::buildSchema(),
            Parser::parse($queryString),
            [$this->getRule($max)]
        );

        $this->assertEquals($expectedErrors, array_map(['GraphQL\Error\Error', 'formatError'], $errors), $queryString);

        return $errors;
    }

    protected function assertIntrospectionQuery($maxExpected)
    {
        $query = Introspection::getIntrospectionQuery(true);

        $this->assertMaxValue($query, $maxExpected);
    }

    protected function assertIntrospectionTypeMetaFieldQuery($maxExpected)
    {
        $query = '
          {
            __type(name: "Human") {
              name
            }
          }
        ';

        $this->assertMaxValue($query, $maxExpected);
    }

    protected function assertTypeNameMetaFieldQuery($maxExpected)
    {
        $query = '
          {
            human {
              __typename
              firstName
            }
          }
        ';
        $this->assertMaxValue($query, $maxExpected);
    }

    protected function assertMaxValue($query, $maxExpected)
    {
        $this->assertDocumentValidator($query, $maxExpected);
        $newMax = $maxExpected - 1;
        if ($newMax !== AbstractQuerySecurity::DISABLED) {
            $this->assertDocumentValidator($query, $newMax, [$this->createFormattedError($newMax, $maxExpected)]);
        }
    }
}
