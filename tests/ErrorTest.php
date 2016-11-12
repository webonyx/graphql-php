<?php

namespace GraphQL\Tests;

use GraphQL\Error\Error;
use GraphQL\Language\Lexer;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;

class ErrorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @it uses the stack of an original error
     */
    public function testUsesTheStackOfAnOriginalError()
    {
        $prev = new \Exception("Original");
        $err = new Error('msg', null, null, null, null, $prev);

        $this->assertSame($err->getPrevious(), $prev);
    }

    /**
     * @it converts nodes to positions and locations
     */
    public function testConvertsNodesToPositionsAndLocations()
    {
        $source = new Source('{
      field
    }');
        $parser = new Parser(new Lexer());
        $ast = $parser->parse($source);
        $fieldAST = $ast->getDefinitions()[0]->getSelectionSet()->getSelections()[0];
        $e = new Error('msg', [ $fieldAST ]);

        $this->assertEquals([$fieldAST], $e->nodes);
        $this->assertEquals($source, $e->getSource());
        $this->assertEquals([8], $e->getPositions());
        $this->assertEquals([new SourceLocation(2, 7)], $e->getLocations());
    }

    /**
     * @it converts node with loc.start === 0 to positions and locations
     */
    public function testConvertsNodeWithStart0ToPositionsAndLocations()
    {
        $source = new Source('{
      field
    }');
        $parser = new Parser(new Lexer());
        $ast = $parser->parse($source);
        $operationAST = $ast->getDefinitions()[0];
        $e = new Error('msg', [ $operationAST ]);

        $this->assertEquals([$operationAST], $e->nodes);
        $this->assertEquals($source, $e->getSource());
        $this->assertEquals([0], $e->getPositions());
        $this->assertEquals([new SourceLocation(1, 1)], $e->getLocations());
    }

    /**
     * @it converts source and positions to locations
     */
    public function testConvertsSourceAndPositionsToLocations()
    {
        $source = new Source('{
      field
    }');
        $e = new Error('msg', null, $source, [ 10 ]);

        $this->assertEquals(null, $e->nodes);
        $this->assertEquals($source, $e->getSource());
        $this->assertEquals([10], $e->getPositions());
        $this->assertEquals([new SourceLocation(2, 9)], $e->getLocations());
    }

    /**
     * @it serializes to include message
     */
    public function testSerializesToIncludeMessage()
    {
        $e = new Error('msg');
        $this->assertEquals(['message' => 'msg'], $e->toSerializableArray());
    }

    /**
     * @it serializes to include message and locations
     */
    public function testSerializesToIncludeMessageAndLocations()
    {
        $parser = new Parser(new Lexer());
        $node = $parser->parse('{ field }')->getDefinitions()[0]->getSelectionSet()->getSelections()[0];
        $e = new Error('msg', [ $node ]);

        $this->assertEquals(
            ['message' => 'msg', 'locations' => [['line' => 1, 'column' => 3]]],
            $e->toSerializableArray()
        );
    }

    /**
     * @it serializes to include path
     */
    public function testSerializesToIncludePath()
    {
        $e = new Error(
            'msg',
            null,
            null,
            null,
            [ 'path', 3, 'to', 'field' ]
        );

        $this->assertEquals([ 'path', 3, 'to', 'field' ], $e->path);
        $this->assertEquals(['message' => 'msg', 'path' => [ 'path', 3, 'to', 'field' ]], $e->toSerializableArray());
    }
}
