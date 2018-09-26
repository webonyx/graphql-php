<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Validator\Rules\QueryDepth;
use function sprintf;
use function str_replace;

class QueryDepthTest extends QuerySecurityTestCase
{
    /**
     * @param int        $queryDepth
     * @param int        $maxQueryDepth
     * @param string[][] $expectedErrors
     *
     * @dataProvider queryDataProvider
     */
    public function testSimpleQueries($queryDepth, $maxQueryDepth = 7, $expectedErrors = []) : void
    {
        $this->assertDocumentValidator($this->buildRecursiveQuery($queryDepth), $maxQueryDepth, $expectedErrors);
    }

    private function buildRecursiveQuery($depth)
    {
        return sprintf('query MyQuery { human%s }', $this->buildRecursiveQueryPart($depth));
    }

    private function buildRecursiveQueryPart($depth)
    {
        $templates = [
            'human' => ' { firstName%s } ',
            'dog'   => ' dogs { name%s } ',
        ];

        $part = $templates['human'];

        for ($i = 1; $i <= $depth; ++$i) {
            $key      = $i % 2 === 1 ? 'human' : 'dog';
            $template = $templates[$key];

            $part = sprintf($part, ($key === 'human' ? ' owner ' : '') . $template);
        }
        $part = str_replace('%s', '', $part);

        return $part;
    }

    /**
     * @param int        $queryDepth
     * @param int        $maxQueryDepth
     * @param string[][] $expectedErrors
     *
     * @dataProvider queryDataProvider
     */
    public function testFragmentQueries($queryDepth, $maxQueryDepth = 7, $expectedErrors = []) : void
    {
        $this->assertDocumentValidator(
            $this->buildRecursiveUsingFragmentQuery($queryDepth),
            $maxQueryDepth,
            $expectedErrors
        );
    }

    private function buildRecursiveUsingFragmentQuery($depth)
    {
        return sprintf(
            'query MyQuery { human { ...F1 } } fragment F1 on Human %s',
            $this->buildRecursiveQueryPart($depth)
        );
    }

    /**
     * @param int        $queryDepth
     * @param int        $maxQueryDepth
     * @param string[][] $expectedErrors
     *
     * @dataProvider queryDataProvider
     */
    public function testInlineFragmentQueries($queryDepth, $maxQueryDepth = 7, $expectedErrors = []) : void
    {
        $this->assertDocumentValidator(
            $this->buildRecursiveUsingInlineFragmentQuery($queryDepth),
            $maxQueryDepth,
            $expectedErrors
        );
    }

    private function buildRecursiveUsingInlineFragmentQuery($depth)
    {
        return sprintf(
            'query MyQuery { human { ...on Human %s } }',
            $this->buildRecursiveQueryPart($depth)
        );
    }

    public function testComplexityIntrospectionQuery() : void
    {
        $this->assertIntrospectionQuery(11);
    }

    public function testIntrospectionTypeMetaFieldQuery() : void
    {
        $this->assertIntrospectionTypeMetaFieldQuery(1);
    }

    public function testTypeNameMetaFieldQuery() : void
    {
        $this->assertTypeNameMetaFieldQuery(1);
    }

    public function queryDataProvider()
    {
        return [
            [1], // Valid because depth under default limit (7)
            [2],
            [3],
            [4],
            [5],
            [6],
            [7],
            [8, 9], // Valid because depth under new limit (9)
            [10, 0], // Valid because 0 depth disable limit
            [
                10,
                8,
                [$this->createFormattedError(8, 10)],
            ], // failed because depth over limit (8)
            [
                20,
                15,
                [$this->createFormattedError(15, 20)],
            ], // failed because depth over limit (15)
        ];
    }

    /**
     * @param int $max
     * @param int $count
     *
     * @return string
     */
    protected function getErrorMessage($max, $count)
    {
        return QueryDepth::maxQueryDepthErrorMessage($max, $count);
    }

    /**
     * @param int $maxDepth
     *
     * @return QueryDepth
     */
    protected function getRule($maxDepth)
    {
        return new QueryDepth($maxDepth);
    }
}
