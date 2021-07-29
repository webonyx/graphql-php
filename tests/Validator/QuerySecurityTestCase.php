<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\Error;
use GraphQL\Language\Parser;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Type\Introspection;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QuerySecurityRule;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function array_map;

abstract class QuerySecurityTestCase extends TestCase
{
    public function testMaxQueryDepthMustBeGreaterOrEqualTo0(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('argument must be greater or equal to 0.');

        $this->getRule(-1);
    }

    /**
     * @param int $max
     *
     * @return QuerySecurityRule
     */
    abstract protected function getRule($max);

    protected function assertIntrospectionQuery($maxExpected)
    {
        $query = Introspection::getIntrospectionQuery();

        $this->assertMaxValue($query, $maxExpected);
    }

    protected function assertMaxValue($query, $maxExpected)
    {
        $this->assertDocumentValidator($query, $maxExpected);
        $newMax = $maxExpected - 1;
        if ($newMax === QuerySecurityRule::DISABLED) {
            return;
        }

        $this->assertDocumentValidator($query, $newMax, [$this->createFormattedError($newMax, $maxExpected)]);
    }

    /**
     * @param string     $queryString
     * @param int        $max
     * @param string[][] $expectedErrors
     *
     * @return Error[]
     */
    protected function assertDocumentValidator($queryString, $max, array $expectedErrors = []): array
    {
        $errors = DocumentValidator::validate(
            QuerySecuritySchema::buildSchema(),
            Parser::parse($queryString),
            [$this->getRule($max)]
        );

        self::assertEquals($expectedErrors, array_map([Error::class, 'formatError'], $errors), $queryString);

        return $errors;
    }

    protected function createFormattedError($max, $count, $locations = [])
    {
        return ErrorHelper::create($this->getErrorMessage($max, $count), $locations);
    }

    /**
     * @param int $max
     * @param int $count
     *
     * @return string
     */
    abstract protected function getErrorMessage($max, $count);

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
}
