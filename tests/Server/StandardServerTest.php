<?php
namespace GraphQL\Tests\Server;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Helper;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Tests\Server\Psr7\PsrRequestStub;
use GraphQL\Tests\Server\Psr7\PsrStreamStub;

class StandardServerTest extends TestCase
{
    /**
     * @var ServerConfig
     */
    private $config;

    public function setUp()
    {
        $schema = $this->buildSchema();
        $this->config = ServerConfig::create()
            ->setSchema($schema);
    }

    public function testSimpleRequestExecutionWithOutsideParsing()
    {
        $body = json_encode([
            'query' => '{f1}'
        ]);

        $parsedBody = $this->parseRawRequest('application/json', $body);
        $server = new StandardServer($this->config);

        $result = $server->executeRequest($parsedBody);
        $expected = [
            'data' => [
                'f1' => 'f1',
            ]
        ];

        $this->assertEquals($expected, $result->toArray(true));
    }

    public function testSimplePsrRequestExecution()
    {
        $body = json_encode([
            'query' => '{f1}'
        ]);

        $expected = [
            'data' => [
                'f1' => 'f1'
            ]
        ];

        $preParsedRequest = $this->preparePsrRequest('application/json', $body, true);
        $this->assertPsrRequestEquals($expected, $preParsedRequest);

        $notPreParsedRequest = $this->preparePsrRequest('application/json', $body, false);
        $this->assertPsrRequestEquals($expected, $notPreParsedRequest);
    }

    private function executePsrRequest($psrRequest)
    {
        $server = new StandardServer($this->config);
        $result = $server->executePsrRequest($psrRequest);
        $this->assertInstanceOf(ExecutionResult::class, $result);
        return $result;
    }

    private function assertPsrRequestEquals($expected, $request)
    {
        $result = $this->executePsrRequest($request);
        $this->assertArraySubset($expected, $result->toArray(true));
        return $result;
    }

    private function preparePsrRequest($contentType, $content, $preParseBody)
    {
        $psrRequestBody = new PsrStreamStub();
        $psrRequestBody->content = $content;

        $psrRequest = new PsrRequestStub();
        $psrRequest->headers['content-type'] = [$contentType];
        $psrRequest->method = 'POST';
        $psrRequest->body = $psrRequestBody;

        if ($preParseBody && $contentType === 'application/json') {
            $parsedBody = json_decode($content, true);
        } else {
            $parsedBody = [];
        }

        $psrRequest->parsedBody = $parsedBody;
        return $psrRequest;
    }

    private function parseRawRequest($contentType, $content, $method = 'POST')
    {
        $_SERVER['CONTENT_TYPE'] = $contentType;
        $_SERVER['REQUEST_METHOD'] = $method;

        $helper = new Helper();
        return $helper->parseHttpRequest(function() use ($content) {
            return $content;
        });
    }
}
