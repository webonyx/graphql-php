<?php

declare(strict_types=1);

namespace GraphQL\Tests\Server;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use Nyholm\Psr7\Request;
use Psr\Http\Message\RequestInterface;

use function json_encode;

class StandardServerTest extends ServerTestCase
{
    use ArraySubsetAsserts;

    /** @var ServerConfig */
    private $config;

    public function setUp(): void
    {
        $schema       = $this->buildSchema();
        $this->config = ServerConfig::create()
            ->setSchema($schema);
    }

    public function testSimpleRequestExecutionWithOutsideParsing(): void
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

    private function parseRawRequest(string $contentType, string $content, string $method = 'POST'): OperationParams
    {
        $_SERVER['CONTENT_TYPE']   = $contentType;
        $_SERVER['REQUEST_METHOD'] = $method;

        $helper = new Helper();

        return $helper->parseHttpRequest(static fn () => $content);
    }

    public function testSimplePsrRequestExecution(): void
    {
        $body = ['query' => '{f1}'];

        $expected = [
            'data' => ['f1' => 'f1'],
        ];

        $request = $this->preparePsrRequest('application/json', json_encode($body));
        $this->assertPsrRequestEquals($expected, $request);
    }

    private function preparePsrRequest(string $contentType, string $body): RequestInterface
    {
        return new Request(
            'POST',
            '',
            ['Content-Type' => $contentType],
            $body
        );
    }

    /**
     * @param array<string, mixed> $expected
     */
    private function assertPsrRequestEquals(array $expected, RequestInterface $request): ExecutionResult
    {
        $result = $this->executePsrRequest($request);
        self::assertArraySubset($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));

        return $result;
    }

    private function executePsrRequest(RequestInterface $psrRequest): ExecutionResult
    {
        return (new StandardServer($this->config))->executePsrRequest($psrRequest);
    }

    public function testMultipleOperationPsrRequestExecution(): void
    {
        $body = [
            'query'         => 'query firstOp {fieldWithPhpError} query secondOp {f1}',
            'operationName' => 'secondOp',
        ];

        $expected = [
            'data' => ['f1' => 'f1'],
        ];

        $request = $this->preparePsrRequest('application/json', json_encode($body));
        $this->assertPsrRequestEquals($expected, $request);
    }
}
