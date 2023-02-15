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

        $nodeList = new NodeList([$nameNode->toArray()]);

        self::assertEquals($nameNode, $nodeList->get(0));
    }

    public function testCloneDeep(): void
    {
        $nameNode = new NameNode(['value' => 'bar']);

        $nodeList = new NodeList([
            $nameNode->toArray(),
            $nameNode,
        ]);

        $cloned = $nodeList->cloneDeep();

        self::assertNotSameButEquals($nodeList->get(0), $cloned->get(0));
        self::assertNotSameButEquals($nodeList->get(1), $cloned->get(1));
    }

    private static function assertNotSameButEquals(object $node, object $clone): void
    {
        self::assertNotSame($node, $clone);
        self::assertEquals($node, $clone);
    }

    public function testThrowsOnInvalidArrays(): void
    {
        $this->expectException(InvariantViolation::class);

        // @phpstan-ignore-next-line Wrong on purpose
        new NodeList([['not a valid array representation of an AST node']]);
    }

    public function testAddNodes(): void
    {
        /** @var NodeList<NameNode> $nodeList */
        $nodeList = new NodeList([]);
        self::assertCount(0, $nodeList);

        $nodeList->add(new NameNode(['value' => 'foo']));
        self::assertCount(1, $nodeList);

        $nodeList->add(new NameNode(['value' => 'bar']));
        self::assertCount(2, $nodeList);
    }

    public function testRemoveDoesNotBreakList(): void
    {
        $foo = new NameNode(['value' => 'foo']);
        $bar = new NameNode(['value' => 'bar']);

        $nodeList = new NodeList([$foo, $bar]);
        $nodeList->remove($foo);

        self::assertTrue($nodeList->has(0));
        self::assertSame($bar, $nodeList->get(0));
    }
}
