<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use PHPUnit\Framework\TestCase;

final class NodeListTest extends TestCase
{
    public function testConvertArrayToASTNode(): void
    {
        $nameNode = new NameNode(['value' => 'bar']);

        $key = 'foo';
        $nodeList = new NodeList([$key => $nameNode->toArray()]);

        self::assertEquals($nameNode, $nodeList[$key]);
    }

    public function testCloneDeep(): void
    {
        $nameNode = new NameNode(['value' => 'bar']);

        $nodeList = new NodeList([
            'array' => $nameNode->toArray(),
            'instance' => $nameNode,
        ]);

        $cloned = $nodeList->cloneDeep();

        self::assertNotSameButEquals($nodeList['array'], $cloned['array']);
        self::assertNotSameButEquals($nodeList['instance'], $cloned['instance']);
    }

    private static function assertNotSameButEquals(object $node, object $clone): void
    {
        self::assertNotSame($node, $clone);
        self::assertEquals($node, $clone);
    }

    public function testThrowsOnInvalidArrays(): void
    {
        $nodeList = new NodeList([]);

        self::expectException(InvariantViolation::class);
        // @phpstan-ignore-next-line Wrong on purpose
        $nodeList[] = ['not a valid array representation of an AST node'];
    }

    public function testPushNodes(): void
    {
        /** @var NodeList<NameNode> $nodeList */
        $nodeList = new NodeList([]);
        self::assertCount(0, $nodeList);

        $nodeList[] = new NameNode(['value' => 'foo']);
        self::assertCount(1, $nodeList);

        $nodeList[] = new NameNode(['value' => 'bar']);
        self::assertCount(2, $nodeList);
    }

    public function testResetContiguousNumericIndexAfterUnset(): void
    {
        $foo = new NameNode(['value' => 'foo']);
        $bar = new NameNode(['value' => 'bar']);
        $baz = new NameNode(['value' => 'baz']);

        $nodeList = new NodeList([$foo, $bar, $baz]);
        unset($nodeList[0]);

        self::assertArrayNotHasKey(0, $nodeList);
        self::assertSame($bar, $nodeList[1]);
        self::assertSame($baz, $nodeList[2]);

        $nodeList->reindex();

        self::assertSame($bar, $nodeList[0]);
        self::assertSame($baz, $nodeList[1]);

        $nodeList->offsetUnset(0);
        self::assertArrayNotHasKey(0, $nodeList);
        self::assertSame($baz, $nodeList[1]);

        $nodeList->reindex();
        self::assertSame($baz, $nodeList[0]);
    }
}
