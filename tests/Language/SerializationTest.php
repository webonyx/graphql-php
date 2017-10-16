<?php
namespace GraphQL\Tests;

use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;

class SerializationTest extends \PHPUnit_Framework_TestCase
{
    public function testSerializesAst()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);
        $expectedAst = json_decode(file_get_contents(__DIR__ . '/kitchen-sink.ast'), true);
        $this->assertEquals($expectedAst, $ast->toArray(true));
    }

    public function testUnserializesAst()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $serializedAst = json_decode(file_get_contents(__DIR__ . '/kitchen-sink.ast'), true);
        $actualAst = AST::fromArray($serializedAst);
        $parsedAst = Parser::parse($kitchenSink);
        $this->assertNodesAreEqual($parsedAst, $actualAst);
    }

    /**
     * Compares two nodes by actually iterating over all NodeLists, properly comparing locations (ignoring tokens), etc
     *
     * @param $expected
     * @param $actual
     * @param array $path
     */
    private function assertNodesAreEqual($expected, $actual, $path = [])
    {
        $err = "Mismatch at AST path: " . implode(', ', $path);

        $this->assertInstanceOf(Node::class, $actual, $err);
        $this->assertEquals(get_class($expected), get_class($actual), $err);

        $expectedVars = get_object_vars($expected);
        $actualVars = get_object_vars($actual);
        $this->assertSame(count($expectedVars), count($actualVars), $err);
        $this->assertEquals(array_keys($expectedVars), array_keys($actualVars), $err);

        foreach ($expectedVars as $name => $expectedValue) {
            $actualValue = $actualVars[$name];
            $tmpPath = $path;
            $tmpPath[] = $name;
            $err = "Mismatch at AST path: " . implode(', ', $tmpPath);

            if ($expectedValue instanceof Node) {
                $this->assertNodesAreEqual($expectedValue, $actualValue, $tmpPath);
            } else if ($expectedValue instanceof NodeList) {
                $this->assertEquals(count($expectedValue), count($actualValue), $err);
                $this->assertInstanceOf(NodeList::class, $actualValue, $err);

                foreach ($expectedValue as $index => $listNode) {
                    $tmpPath2 = $tmpPath;
                    $tmpPath2 [] = $index;
                    $this->assertNodesAreEqual($listNode, $actualValue[$index], $tmpPath2);
                }
            } else if ($expectedValue instanceof Location) {
                $this->assertInstanceOf(Location::class, $actualValue, $err);
                $this->assertSame($expectedValue->start, $actualValue->start, $err);
                $this->assertSame($expectedValue->end, $actualValue->end, $err);
            } else {
                $this->assertEquals($expectedValue, $actualValue, $err);
            }
        }
    }
}
