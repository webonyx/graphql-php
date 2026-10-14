<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

use function Safe\file_get_contents;

final class SerializationTest extends TestCase
{
    public function testSerializesAst(): void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $parsedAst = Parser::parse($kitchenSink);
        $expectedAst = json_decode(file_get_contents(__DIR__ . '/kitchen-sink.ast'), true);
        self::assertEquals($expectedAst, AST::toArray($parsedAst));
    }

    public function testUnserializesAst(): void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $parsedAst = Parser::parse($kitchenSink);
        $serializedAst = json_decode(file_get_contents(__DIR__ . '/kitchen-sink.ast'), true);
        self::assertNodesAreEqual($parsedAst, AST::fromArray($serializedAst));
    }

    /**
     * Compares two nodes by actually iterating over all NodeLists, properly comparing locations (ignoring tokens), etc.
     *
     * @param array<string> $path
     */
    private static function assertNodesAreEqual(Node $expected, Node $actual, array $path = []): void
    {
        $err = 'Mismatch at AST path: ' . implode(', ', $path);

        self::assertInstanceOf(get_class($expected), $actual, $err);

        $expectedVars = get_object_vars($expected);
        $actualVars = get_object_vars($actual);
        self::assertEquals(array_keys($expectedVars), array_keys($actualVars), $err);

        foreach ($expectedVars as $name => $expectedValue) {
            $actualValue = $actualVars[$name];
            $tmpPath = $path;
            $tmpPath[] = $name;
            $err = 'Mismatch at AST path: ' . implode(', ', $tmpPath);

            if ($expectedValue instanceof Node) {
                self::assertNodesAreEqual($expectedValue, $actualValue, $tmpPath);
            } elseif ($expectedValue instanceof NodeList) {
                self::assertInstanceOf(NodeList::class, $actualValue, $err);
                self::assertCount(count($expectedValue), $actualValue, $err);

                foreach ($expectedValue as $index => $listNode) {
                    $tmpPath2 = $tmpPath;
                    $tmpPath2[] = $index;
                    self::assertNodesAreEqual($listNode, $actualValue[$index], $tmpPath2);
                }
            } elseif ($expectedValue instanceof Location) {
                self::assertInstanceOf(Location::class, $actualValue, $err);
                self::assertSame($expectedValue->start, $actualValue->start, $err);
                self::assertSame($expectedValue->end, $actualValue->end, $err);
            } else {
                self::assertEquals($expectedValue, $actualValue, $err);
            }
        }
    }

    public function testSerializeSupportsNoLocationOption(): void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $parsedAst = Parser::parse($kitchenSink, ['noLocation' => true]);
        $expectedAst = json_decode(file_get_contents(__DIR__ . '/kitchen-sink-noloc.ast'), true);
        self::assertEquals($expectedAst, $parsedAst->toArray());
    }

    public function testUnserializeSupportsNoLocationOption(): void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $parsedAst = Parser::parse($kitchenSink, ['noLocation' => true]);
        $serializedAst = json_decode(file_get_contents(__DIR__ . '/kitchen-sink-noloc.ast'), true);
        self::assertNodesAreEqual($parsedAst, AST::fromArray($serializedAst));
    }
}
