<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Validator\Rules\QueryDepth;

use function sprintf;
use function str_replace;

final class QueryDepthTest extends QuerySecurityTestCase
{
    /**
     * @param array<int, array<string, mixed>> $expectedErrors
     *
     * @dataProvider queryDataProvider
     */
    public function testSimpleQueries(int $queryDepth, int $maxQueryDepth = 7, array $expectedErrors = []): void
    {
        $this->assertDocumentValidator($this->buildRecursiveQuery($queryDepth), $maxQueryDepth, $expectedErrors);
    }

    private function buildRecursiveQuery(int $depth): string
    {
        return "query MyQuery { human{$this->buildRecursiveQueryPart($depth)} }";
    }

    private function buildRecursiveQueryPart(int $depth): string
    {
        $templates = [
            'human' => ' { firstName%s } ',
            'dog' => ' dogs { name%s } ',
        ];

        $part = $templates['human'];

        for ($i = 1; $i <= $depth; ++$i) {
            $key = $i % 2 === 1 ? 'human' : 'dog';
            $template = $templates[$key];

            $part = sprintf($part, ($key === 'human' ? ' owner ' : '') . $template);
        }

        return str_replace('%s', '', $part);
    }

    /**
     * @param array<int, array<string, mixed>> $expectedErrors
     *
     * @dataProvider queryDataProvider
     */
    public function testFragmentQueries(int $queryDepth, int $maxQueryDepth = 7, array $expectedErrors = []): void
    {
        $this->assertDocumentValidator(
            $this->buildRecursiveUsingFragmentQuery($queryDepth),
            $maxQueryDepth,
            $expectedErrors
        );
    }

    private function buildRecursiveUsingFragmentQuery(int $depth): string
    {
        return "query MyQuery { human { ...F1 } } fragment F1 on Human {$this->buildRecursiveQueryPart($depth)}";
    }

    /**
     * @param array<int, array<string, mixed>> $expectedErrors
     *
     * @dataProvider queryDataProvider
     */
    public function testInlineFragmentQueries(int $queryDepth, int $maxQueryDepth = 7, array $expectedErrors = []): void
    {
        $this->assertDocumentValidator(
            $this->buildRecursiveUsingInlineFragmentQuery($queryDepth),
            $maxQueryDepth,
            $expectedErrors
        );
    }

    private function buildRecursiveUsingInlineFragmentQuery(int $depth): string
    {
        return "query MyQuery { human { ...on Human {$this->buildRecursiveQueryPart($depth)} } }";
    }

    public function testComplexityIntrospectionQuery(): void
    {
        $this->assertIntrospectionQuery(11);
    }

    public function testIntrospectionTypeMetaFieldQuery(): void
    {
        $this->assertIntrospectionTypeMetaFieldQuery(1);
    }

    public function testTypeNameMetaFieldQuery(): void
    {
        $this->assertTypeNameMetaFieldQuery(1);
    }

    /**
     * @return array<int, array{0: int, 1?: int, 2?: array<int, array<string, mixed>>}>
     */
    public function queryDataProvider(): array
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

    protected function getErrorMessage(int $max, int $count): string
    {
        return QueryDepth::maxQueryDepthErrorMessage($max, $count);
    }

    protected function getRule(int $max): QueryDepth
    {
        return new QueryDepth($max);
    }
}
