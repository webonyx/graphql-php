<?php declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Server\OperationParams;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use React\Promise\Deferred;

final class HttpServerTest extends ServerTestCase
{
    /** @dataProvider provideResponseNegotiation */
    public function testResponseNegotiation(string $accept, int $statusCode, string $mediaType): void
    {
        $executions = 0;
        $config = ServerConfig::create([
            'schema' => $this->buildSchema(),
            'graphqlOverHttp' => true,
            'context' => static function () use (&$executions) {
                ++$executions;

                return null;
            },
        ]);
        $response = (new StandardServer($config))->processPsrRequest(
            new Request('POST', '/graphql', [
                'Content-Type' => 'application/json',
                'Accept' => $accept,
            ], '{"query":"{ f1 }"}'),
            new Response(202, ['Vary' => 'Origin']),
            Stream::create()
        );
        self::assertInstanceOf(Response::class, $response);
        self::assertSame($statusCode, $response->getStatusCode());
        self::assertSame("{$mediaType}; charset=utf-8", $response->getHeaderLine('Content-Type'));
        self::assertSame('Origin, Accept', $response->getHeaderLine('Vary'));
        self::assertSame($statusCode === 200 ? 1 : 0, $executions);
        self::assertSame(
            $statusCode === 200
                ? ['data' => ['f1' => 'f1']]
                : ['errors' => [['message' => 'No acceptable response media type.']]],
            json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /** @return \Generator<string, array{string, int, string}> */
    public static function provideResponseNegotiation(): \Generator
    {
        yield 'missing Accept' => ['', 200, 'application/graphql-response+json'];
        yield 'modern client' => ['application/graphql-response+json', 200, 'application/graphql-response+json'];
        yield 'legacy client' => ['application/json', 200, 'application/json'];
        yield 'client quality preference' => ['application/graphql-response+json;q=0.5, application/json', 200, 'application/json'];
        yield 'client order for equal quality' => ['application/json, application/graphql-response+json', 200, 'application/json'];
        yield 'wildcard defaults to modern' => ['*/*', 200, 'application/graphql-response+json'];
        yield 'specific exclusion overrides wildcard' => ['application/graphql-response+json;q=0, application/*', 200, 'application/json'];
        yield 'exact range precedes parameterized wildcard' => ['application/graphql-response+json;q=0, application/*;charset=utf-8', 200, 'application/json'];
        yield 'UTF-8 parameter and case' => ['Application/GraphQL-Response+JSON; charset="UTF-8"', 200, 'application/graphql-response+json'];
        yield 'escaped charset character' => ['application/graphql-response+json; charset="utf\\-8"', 200, 'application/graphql-response+json'];
        yield 'unterminated quoted charset' => ['application/graphql-response+json; charset="utf-8', 406, 'application/json'];
        yield 'no acceptable type' => ['text/html', 406, 'application/json'];
        yield 'quoted comma is not a media range' => ['text/html; note="one,application/json,two"', 406, 'application/json'];
        yield 'escaped quote keeps commas quoted' => ['text/html; note="one\\",application/json,two"', 406, 'application/json'];
        yield 'escaped backslash before a real range' => ['text/html; note="one\\\\", application/json', 200, 'application/json'];
        yield 'all supported types excluded' => ['application/json;q=0, application/graphql-response+json;q=0, */*', 406, 'application/json'];
        yield 'unsupported charset' => ['application/graphql-response+json;charset=iso-8859-1', 406, 'application/json'];
        yield 'invalid quality is not accepted' => ['application/graphql-response+json;q=2', 406, 'application/json'];
    }

    public function testLegacyBehaviorRemainsTheDefault(): void
    {
        $server = new StandardServer(['schema' => $this->buildSchema()]);
        $response = $server->processPsrRequest(
            new Request('POST', '/graphql', ['Content-Type' => 'application/json', 'Accept' => 'text/html'], '{"query":"{ f1 }"}'),
            new Response(202),
            Stream::create()
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertFalse($response->hasHeader('Vary'));
        self::assertSame('{"data":{"f1":"f1"}}', $response->getBody()->__toString());
    }

    public function testNegotiationIsRetainedUntilAsyncExecutionCompletes(): void
    {
        $deferred = new Deferred();
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'value' => [
                        'type' => Type::string(),
                        'resolve' => static fn () => $deferred->promise(),
                    ],
                ],
            ]),
        ]);
        $server = new StandardServer(ServerConfig::create()
            ->setSchema($schema)
            ->setPromiseAdapter(new ReactPromiseAdapter())
            ->setGraphQLOverHttp(true));

        $pending = $server->processPsrRequest(
            new Request('GET', '/graphql?query=%7Bvalue%7D', ['Accept' => 'application/graphql-response+json']),
            new Response(),
            Stream::create()
        );
        self::assertInstanceOf(Promise::class, $pending);
        $response = null;
        $pending->then(static function ($resolved) use (&$response): void {
            $response = $resolved;
        });
        self::assertNull($response);
        $deferred->resolve('done');

        /** @var Response|null $response The promise callback sets this value. */
        self::assertInstanceOf(Response::class, $response);
        self::assertSame('application/graphql-response+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"data":{"value":"done"}}', $response->getBody()->__toString());
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testGlobalRequestRejectsUnacceptableMediaBeforeExecution(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $executed = false;
        $server = new StandardServer(ServerConfig::create()
            ->setSchema($this->buildSchema())
            ->setGraphQLOverHttp(true)
            ->setContext(static function () use (&$executed): void {
                $executed = true;
            }));

        ob_start();
        $server->handleRequest(OperationParams::create(['query' => '{ f1 }']));
        $body = ob_get_clean();

        self::assertSame(406, http_response_code());
        self::assertFalse($executed);
        self::assertSame('{"errors":[{"message":"No acceptable response media type."}]}', $body);
    }
}
