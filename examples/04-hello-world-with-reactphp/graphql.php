<?php declare(strict_types=1);

// Run local test server
// php graphql.php

// Try query
// curl -d '{"query": "query { echo(message: \"Hello World\") }" }' -H "Content-Type: application/json" http://localhost:8080

// Try mutation
// curl -d '{"query": "mutation { sum(x: 2, y: 2) }" }' -H "Content-Type: application/json" http://localhost:8080

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

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
                    $promise = $promise->then(function () use ($rootValue, $args) {
                        return $rootValue['prefix'] . $args['message'];
                    });
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

    $react = new \GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter();
    $server = new \React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) use ($schema, $react) {
        $rawInput = (string) $request->getBody();
        $input = json_decode($rawInput, true);
        $query = $input['query'];
        $variableValues = $input['variables'] ?? null;
        $rootValue = ['prefix' => 'You said: '];
        $promise = GraphQL::promiseToExecute($react, $schema, $query, $rootValue, null, $variableValues);

        return $promise->then(function (\GraphQL\Executor\ExecutionResult $result) {
            $output = $result->toArray(1);

            return new \React\Http\Message\Response(
                200,
                [
                    'Content-Type' => 'text/json',
                ],
                (json_encode($output)!==false) ? json_encode($output) : ''
            );
        });
    });
    $socket = new \React\Socket\SocketServer('127.0.0.1:8010');
    $server->listen($socket);
    echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress() ?? '') . PHP_EOL;
