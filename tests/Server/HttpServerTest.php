<?php declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Error\InvariantViolation;
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

    /**
     * @dataProvider provideRequestFailures
     *
     * @phpstan-param list<array<string, mixed>>|null $expectedErrors
     */
    public function testRequestFailureStatus(
        Request $request,
        int $statusCode,
        ?string $allow,
        ?ServerConfig $config = null,
        ?array $expectedErrors = null
    ): void {
        $server = new StandardServer(($config ?? ServerConfig::create())
            ->setSchema($this->buildSchema())
            ->setGraphQLOverHttp(true));
        $response = $server->processPsrRequest($request, new Response(), Stream::create());

        self::assertInstanceOf(Response::class, $response);
        self::assertSame($statusCode, $response->getStatusCode());
        self::assertSame('application/graphql-response+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame($allow ?? '', $response->getHeaderLine('Allow'));
        $body = json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('data', $body);
        if ($expectedErrors === null) {
            self::assertNotEmpty($body['errors']);
        } else {
            self::assertSame($expectedErrors, $body['errors']);
        }
    }

    /**
     * @throws InvariantViolation
     *
     * @phpstan-return \Generator<string, array{
     *     Request,
     *     int,
     *     ?string,
     *     3?: ServerConfig,
     *     4?: list<array<string, mixed>>
     * }>
     */
    public static function provideRequestFailures(): \Generator
    {
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        yield 'document syntax' => [new Request('POST', '/graphql', $headers, '{"query":"{"}'), 400, null];
        yield 'validation' => [new Request('POST', '/graphql', $headers, '{"query":"{ missing }"}'), 422, null];
        yield 'operation selection' => [new Request('POST', '/graphql', $headers, '{"query":"query A {f1} query B {f1}"}'), 422, null];
        yield 'variable coercion' => [new Request('POST', '/graphql', $headers, '{"query":"query($v:String!){fieldWithArg(arg:$v)}"}'), 422, null];
        yield 'unsupported method' => [new Request('PUT', '/graphql', $headers, '{"query":"{f1}"}'), 405, 'GET, POST'];
        yield 'unsupported method without content type' => [new Request('DELETE', '/graphql'), 405, 'GET, POST'];
        yield 'unsupported method with malformed JSON' => [new Request('DELETE', '/graphql', $headers, '{'), 405, 'GET, POST'];
        yield 'missing content type' => [new Request('POST', '/graphql', ['Accept' => 'application/json'], '{"query":"{f1}"}'), 415, null];
        yield 'malformed JSON' => [new Request('POST', '/graphql', $headers, '{'), 400, null];
        yield 'filtered malformed JSON errors' => [
            new Request('POST', '/graphql', $headers, '{'),
            400,
            null,
            ServerConfig::create()->setErrorsHandler(static fn () => []),
            [['message' => 'GraphQL request failed.']],
        ];
        yield 'filtered validation errors' => [
            new Request('POST', '/graphql', $headers, '{"query":"{ missing }"}'),
            422,
            null,
            ServerConfig::create()->setErrorsHandler(static fn () => []),
            [['message' => 'GraphQL request failed.']],
        ];
        $formattedError = ['message' => 'Custom request error.', 'extensions' => ['code' => 'BAD_REQUEST']];
        yield 'formatted malformed JSON error' => [
            new Request('POST', '/graphql', $headers, '{'),
            400,
            null,
            ServerConfig::create()->setErrorFormatter(static fn () => $formattedError),
            [$formattedError],
        ];
    }

    public function testGetCannotExecuteAMutation(): void
    {
        $writes = 0;
        $schema = new Schema([
            'query' => new ObjectType(['name' => 'Query', 'fields' => ['value' => Type::string()]]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => [
                    'write' => [
                        'type' => Type::int(),
                        'resolve' => static function () use (&$writes): int {
                            return ++$writes;
                        },
                    ],
                ],
            ]),
        ]);
        $server = new StandardServer(ServerConfig::create()->setSchema($schema)->setGraphQLOverHttp(true));
        $get = $server->processPsrRequest(
            new Request('GET', '/graphql?query=mutation%7Bwrite%7D'),
            new Response(),
            Stream::create()
        );
        self::assertInstanceOf(Response::class, $get);
        self::assertSame(405, $get->getStatusCode());
        self::assertSame('POST', $get->getHeaderLine('Allow'));
        self::assertSame(0, $writes);

        $post = $server->processPsrRequest(
            new Request('POST', '/graphql', ['Content-Type' => 'application/json'], '{"query":"mutation{write}"}'),
            new Response(),
            Stream::create()
        );
        self::assertInstanceOf(Response::class, $post);
        self::assertSame(200, $post->getStatusCode());
        self::assertSame('{"data":{"write":1}}', $post->getBody()->__toString());
    }

    /** @dataProvider provideErrorFiltering */
    public function testNullExecutionDataIsNotARequestFailure(bool $filterErrors): void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'required' => [
                        'type' => Type::nonNull(Type::string()),
                        'resolve' => static fn () => null,
                    ],
                ],
            ]),
        ]);
        $config = ServerConfig::create()->setSchema($schema)->setGraphQLOverHttp(true);
        if ($filterErrors) {
            $config->setErrorsHandler(static fn () => []);
        }

        $response = (new StandardServer($config))->processPsrRequest(
            new Request('POST', '/graphql', ['Content-Type' => 'application/json'], '{"query":"{ required }"}'),
            new Response(),
            Stream::create()
        );
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('data', $body);
        self::assertNull($body['data']);
        self::assertSame(
            $filterErrors ? 'GraphQL request failed.' : 'Internal server error',
            $body['errors'][0]['message']
        );
    }

    /** @return \Generator<string, array{bool}> */
    public static function provideErrorFiltering(): \Generator
    {
        yield 'default masking' => [false];
        yield 'custom filtering preserves a well-formed result' => [true];
    }

    public function testEmptyExecutionDataIsAnObject(): void
    {
        $server = new StandardServer(ServerConfig::create()
            ->setSchema($this->buildSchema())
            ->setGraphQLOverHttp(true));
        $response = $server->processPsrRequest(
            new Request('POST', '/graphql', ['Content-Type' => 'application/json'], '{"query":"{ f1 @skip(if: true) }"}'),
            new Response(),
            Stream::create()
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"data":{}}', $response->getBody()->__toString());
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
