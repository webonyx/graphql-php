<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Validator\Rules\QueryDepth;

class QueryDepthTest extends AbstractQuerySecurityTest
{
    /**
     * @param $max
     * @param $count
     *
     * @return string
     */
    protected function getErrorMessage($max, $count)
    {
        return QueryDepth::maxQueryDepthErrorMessage($max, $count);
    }

    /**
     * @param $maxDepth
     *
     * @return QueryDepth
     */
    protected function getRule($maxDepth)
    {
        return new QueryDepth($maxDepth);
    }

    /**
     * @param $queryDepth
     * @param int   $maxQueryDepth
     * @param array $expectedErrors
     * @dataProvider queryDataProvider
     */
    public function testSimpleQueries($queryDepth, $maxQueryDepth = 7, $expectedErrors = [])
    {
        $this->assertDocumentValidator($this->buildRecursiveQuery($queryDepth), $maxQueryDepth, $expectedErrors);
    }

    /**
     * @param $queryDepth
     * @param int   $maxQueryDepth
     * @param array $expectedErrors
     * @dataProvider queryDataProvider
     */
    public function testFragmentQueries($queryDepth, $maxQueryDepth = 7, $expectedErrors = [])
    {
        $this->assertDocumentValidator($this->buildRecursiveUsingFragmentQuery($queryDepth), $maxQueryDepth, $expectedErrors);
    }

    /**
     * @param $queryDepth
     * @param int   $maxQueryDepth
     * @param array $expectedErrors
     * @dataProvider queryDataProvider
     */
    public function testInlineFragmentQueries($queryDepth, $maxQueryDepth = 7, $expectedErrors = [])
    {
        $this->assertDocumentValidator($this->buildRecursiveUsingInlineFragmentQuery($queryDepth), $maxQueryDepth, $expectedErrors);
    }

    public function testComplexityIntrospectionQuery()
    {
        $this->assertIntrospectionQuery(11);
    }

    public function testIntrospectionTypeMetaFieldQuery()
    {
        $this->assertIntrospectionTypeMetaFieldQuery(1);
    }

    public function testTypeNameMetaFieldQuery()
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

    private function buildRecursiveQuery($depth)
    {
        $query = sprintf('query MyQuery { human%s }', $this->buildRecursiveQueryPart($depth));

        return $query;
    }

    private function buildRecursiveUsingFragmentQuery($depth)
    {
        $query = sprintf(
            'query MyQuery { human { ...F1 } } fragment F1 on Human %s',
            $this->buildRecursiveQueryPart($depth)
        );

        return $query;
    }

    private function buildRecursiveUsingInlineFragmentQuery($depth)
    {
        $query = sprintf(
            'query MyQuery { human { ...on Human %s } }',
            $this->buildRecursiveQueryPart($depth)
        );

        return $query;
    }

    private function buildRecursiveQueryPart($depth)
    {
        $templates = [
            'human' => ' { firstName%s } ',
            'dog' => ' dogs { name%s } ',
        ];

        $part = $templates['human'];

        for ($i = 1; $i <= $depth; ++$i) {
            $key = ($i % 2 == 1) ? 'human' : 'dog';
            $template = $templates[$key];

            $part = sprintf($part, ('human' == $key ? ' owner ' : '').$template);
        }
        $part = str_replace('%s', '', $part);

        return $part;
    }
}
