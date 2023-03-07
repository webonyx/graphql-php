<?php declare(strict_types=1);

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

use function Safe\json_encode;

final class StandardServerTest extends ServerTestCase
{
    use ArraySubsetAsserts;

    /** @var ServerConfig */
    private $config;

    public function setUp(): void
    {
        $schema = $this->buildSchema();
        $this->config = ServerConfig::create()
            ->setSchema($schema);
    }

    public function testSimpleRequestExecutionWithOutsideParsing(): void
    {
        $body = json_encode(['query' => '{f1}'], JSON_THROW_ON_ERROR);

        $parsedBody = $this->parseRawRequest('application/json', $body);
        $server = new StandardServer($this->config);

        $result = $server->executeRequest($parsedBody);

        self::assertInstanceOf(ExecutionResult::class, $result);
        self::assertSame(
            [
                'data' => ['f1' => 'f1'],
            ],
            $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
    }

    /**
     * @throws \GraphQL\Server\RequestError
     * @throws \JsonException
     */
    private function parseRawRequest(string $contentType, string $content, string $method = 'POST'): OperationParams
    {
        $_SERVER['CONTENT_TYPE'] = $contentType;
        $_SERVER['REQUEST_METHOD'] = $method;

        $helper = new Helper();

        $operationParams = $helper->parseHttpRequest(static fn () => $content);
        self::assertInstanceOf(OperationParams::class, $operationParams);

        return $operationParams;
    }

    public function testSimplePsrRequestExecution(): void
    {
        $body = ['query' => '{f1}'];

        $expected = [
            'data' => ['f1' => 'f1'],
        ];

        $request = $this->preparePsrRequest('application/json', json_encode($body, JSON_THROW_ON_ERROR));
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
     *
     * @throws \Exception
     */
    private function assertPsrRequestEquals(array $expected, RequestInterface $request): ExecutionResult
    {
        $result = $this->executePsrRequest($request);
        self::assertArraySubset($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));

        return $result;
    }

    /**
     * @throws \Exception
     * @throws \JsonException
     */
    private function executePsrRequest(RequestInterface $psrRequest): ExecutionResult
    {
        $result = (new StandardServer($this->config))->executePsrRequest($psrRequest);
        self::assertInstanceOf(ExecutionResult::class, $result);

        return $result;
    }

    public function testMultipleOperationPsrRequestExecution(): void
    {
        $body = [
            'query' => 'query firstOp {fieldWithPhpError} query secondOp {f1}',
            'operationName' => 'secondOp',
        ];

        $expected = [
            'data' => ['f1' => 'f1'],
        ];

        $request = $this->preparePsrRequest('application/json', json_encode($body, JSON_THROW_ON_ERROR));
        $this->assertPsrRequestEquals($expected, $request);
    }
}
