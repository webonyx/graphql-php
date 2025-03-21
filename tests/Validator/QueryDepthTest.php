<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Validator\Rules\QueryDepth;

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
        $human = ' { firstName%s } ';
        $dog = ' dogs { name%s } ';

        $part = $human;

        foreach (range(1, $depth) as $i) {
            $isOdd = $i % 2 === 1;
            $template = $isOdd
                ? $human
                : $dog;

            $part = sprintf($part, ($isOdd ? ' owner ' : '') . $template);
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

    public function testInfiniteRecursion(): void
    {
        $query = 'query MyQuery { human { ...F1 } } fragment F1 on Human { ...F1 }';
        $this->assertDocumentValidator($query, 7, [self::createFormattedError(7, 8)]);
    }

    /** @return iterable<array{0: int, 1?: int, 2?: array<int, array<string, mixed>>}> */
    public static function queryDataProvider(): iterable
    {
        yield [1]; // Valid because depth under default limit (7)
        yield [2];
        yield [3];
        yield [4];
        yield [5];
        yield [6];
        yield [7];
        yield [8, 9]; // Valid because depth under new limit (9)
        yield [10, 0]; // Valid because 0 depth disable limit
        yield [
            10,
            8,
            [self::createFormattedError(8, 10)],
        ]; // failed because depth over limit (8)
        yield [
            20,
            15,
            [self::createFormattedError(15, 20)],
        ]; // failed because depth over limit (15)
    }

    protected static function getErrorMessage(int $max, int $count): string
    {
        return QueryDepth::maxQueryDepthErrorMessage($max, $count);
    }

    /** @throws \InvalidArgumentException */
    protected function getRule(int $max): QueryDepth
    {
        return new QueryDepth($max);
    }
}
