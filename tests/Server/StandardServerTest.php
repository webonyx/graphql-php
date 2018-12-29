<?php

declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Helper;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Tests\Server\Psr7\PsrRequestStub;
use function json_encode;

class StandardServerTest extends ServerTestCase
{
    /** @var ServerConfig */
    private $config;

    public function setUp()
    {
        $schema       = $this->buildSchema();
        $this->config = ServerConfig::create()
            ->setSchema($schema);
    }

    public function testSimpleRequestExecutionWithOutsideParsing() : void
    {
        $body = json_encode(['query' => '{f1}']);

        $parsedBody = $this->parseRawRequest('application/json', $body);
        $server     = new StandardServer($this->config);

        $result   = $server->executeRequest($parsedBody);
        $expected = [
            'data' => ['f1' => 'f1'],
        ];

        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
    }

    private function parseRawRequest($contentType, $content, $method = 'POST')
    {
        $_SERVER['CONTENT_TYPE']   = $contentType;
        $_SERVER['REQUEST_METHOD'] = $method;

        $helper = new Helper();

        return $helper->parseHttpRequest(static function () use ($content) {
            return $content;
        });
    }

    public function testSimplePsrRequestExecution() : void
    {
        $body = ['query' => '{f1}'];

        $expected = [
            'data' => ['f1' => 'f1'],
        ];

        $request = $this->preparePsrRequest('application/json', $body);
        $this->assertPsrRequestEquals($expected, $request);
    }

    private function preparePsrRequest($contentType, $parsedBody)
    {
        $psrRequest                          = new PsrRequestStub();
        $psrRequest->headers['content-type'] = [$contentType];
        $psrRequest->method                  = 'POST';
        $psrRequest->parsedBody              = $parsedBody;

        return $psrRequest;
    }

    private function assertPsrRequestEquals($expected, $request)
    {
        $result = $this->executePsrRequest($request);
        self::assertArraySubset($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));

        return $result;
    }

    private function executePsrRequest($psrRequest)
    {
        $server = new StandardServer($this->config);
        $result = $server->executePsrRequest($psrRequest);
        self::assertInstanceOf(ExecutionResult::class, $result);

        return $result;
    }

    public function testMultipleOperationPsrRequestExecution() : void
    {
        $body = [
            'query'         => 'query firstOp {fieldWithPhpError} query secondOp {f1}',
            'operationName' => 'secondOp',
        ];

        $expected = [
            'data' => ['f1' => 'f1'],
        ];

        $request = $this->preparePsrRequest('application/json', $body);
        $this->assertPsrRequestEquals($expected, $request);
    }
}
