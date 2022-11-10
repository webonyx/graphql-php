<?php declare(strict_types=1);

// Run local test server
// php graphql.php

// Try query
// curl -d '{"query": "query { echo(message: \"Hello World\") }" }' -H "Content-Type: application/json" http://localhost:8010

// Try mutation
// curl -d '{"query": "mutation { sum(x: 2, y: 2) }" }' -H "Content-Type: application/json" http://localhost:8010

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter;
use React\Http\HttpServer;
use Psr\Http\Message\ServerRequestInterface;
use React\Socket\SocketServer;
use GraphQL\Executor\ExecutionResult;
use React\Http\Message\Response;
use GraphQL\Error\DebugFlag;
$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'echo' => [
            'type' => Type::string(),
            'args' => [
                'message' => ['type' => Type::string()],
            ],
            'resolve' => function ($rootValue, array $args) {
                $deferred = new \React\Promise\Deferred();
                $promise = $deferred->promise();
                $promise =  $promise = $promise->then(static fn (): string => $rootValue['prefix'] . $args['message']);
                $deferred->resolve();
                return $promise;
            },
        ],
    ],
]);
$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [
        'sum' => [
            'type' => Type::int(),
            'args' => [
                'x' => ['type' => Type::int()],
                'y' => ['type' => Type::int()],
            ],
            'resolve' => static fn ($calc, array $args): int => $args['x'] + $args['y'],
        ],
    ],
]);
 // See docs on schema options:
// https://webonyx.github.io/graphql-php/schema-definition/#configuration-options
$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType,
]);
$react = new ReactPromiseAdapter();
$server = new HttpServer(function (ServerRequestInterface $request) use ($schema, $react) {
    $rawInput = (string) $request->getBody();
    $input = json_decode($rawInput, true);
    $query = $input['query'];
    $variableValues = $input['variables'] ?? null;
    $rootValue = ['prefix' => 'You said: '];
    $promise = GraphQL::promiseToExecute($react, $schema, $query, $rootValue, null, $variableValues);
    return $promise->then(function (ExecutionResult $result) {
        $output = json_encode($result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
        return new Response(
            200,
            [
                'Content-Type' => 'text/json',
            ],
            ($output !== false) ? $output : ''
        );
    });
});
$socket = new SocketServer('127.0.0.1:8010');
$server->listen($socket);
echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress() ?? '') . PHP_EOL;
