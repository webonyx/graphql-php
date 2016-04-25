<?php
namespace GraphQL\Tests\Executor;

use GraphQL\Executor\ExecutionResult;

class ExecutionResultTest extends \PHPUnit_Framework_TestCase
{
    public function testToArrayWithoutExtensions()
    {
        $executionResult = new ExecutionResult();

        $this->assertEquals(['data' => null], $executionResult->toArray());
    }

    public function testToArrayExtensions()
    {
        $executionResult = new ExecutionResult(null, [], ['foo' => 'bar']);

        $this->assertEquals(['data' => null, 'extensions' => ['foo' => 'bar']], $executionResult->toArray());

        $executionResult->extensions = ['bar' => 'foo'];

        $this->assertEquals(['data' => null, 'extensions' => ['bar' => 'foo']], $executionResult->toArray());
    }
}
