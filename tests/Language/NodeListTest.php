<?php

declare(strict_types=1);

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

    public function testThrowsOnInvalidArrays(): void
    {
        $nodeList = new NodeList([]);

        self::expectException(InvariantViolation::class);
        // @phpstan-ignore-next-line Wrong on purpose
        $nodeList[] = ['not a valid array representation of an AST node'];
    }

    public function testPushNodes(): void
    {
        $nodeList = new NodeList([]);
        self::assertCount(0, $nodeList);

        $nodeList[] = new NameNode(['value' => 'foo']);
        self::assertCount(1, $nodeList);

        $nodeList[] = new NameNode(['value' => 'bar']);
        self::assertCount(2, $nodeList);
    }
}
