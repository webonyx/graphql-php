<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use PHPUnit\Framework\TestCase;

use function get_class;

final class NodeListTest extends TestCase
{
    public function testConvertArrayToASTNode(): void
    {
        $nodeList = new NodeList([]);

        $nameNode        = new NameNode(['value' => 'foo']);
        $nodeList['foo'] = $nameNode->toArray();

        self::assertInstanceOf(get_class($nameNode), $nodeList['foo']);
    }

    public function testThrowsOnInvalidArrays(): void
    {
        $nodeList = new NodeList([]);

        self::expectException(InvariantViolation::class);
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
