<?php declare(strict_types=1);

// php graphql.php
// curl -d '{"query": "query { product article }" }' -H "Content-Type: application/json" localhost:8080

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Loop;
use Amp\Socket\Server;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\AmpPromiseAdapter;
use GraphQL\GraphQL;
use Psr\Log\NullLogger;

$schema = require_once __DIR__ . '/../schema.php';

Loop::run(function () use ($schema): Generator {
    $sockets = [
        Server::listen("localhost:8080"),
    ];

    $server = new HttpServer($sockets, new CallableRequestHandler(function (Request $request) use ($schema): Generator {
        $input = json_decode(yield $request->getBody()->buffer(), true);
        $query = $input['query'];
        $variableValues = $input['variables'] ?? null;
        $promise = GraphQL::promiseToExecute(new AmpPromiseAdapter(), $schema, $query, [], null, $variableValues);
        $promise = $promise->then(function(ExecutionResult $result): Response {
            return new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode($result->toArray(), JSON_THROW_ON_ERROR)
            );
        });
        return $promise->adoptedPromise;
    }), new NullLogger);

    yield $server->start();
});
