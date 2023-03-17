<?php declare(strict_types=1);

// php -S localhost:8080 graphql.php
// curl -d '{"query": "query { product article }" }' -H "Content-Type: application/json" localhost:8080

require_once __DIR__ . '/../../../../vendor/autoload.php';

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\AmpPromiseAdapter;
use GraphQL\GraphQL;

$schema = require_once __DIR__ . '/../schema.php';

\Amp\Loop::run(function () use ($schema) {
    $input = json_decode(file_get_contents('php://input'), true);
    $promise = GraphQL::promiseToExecute(
        new AmpPromiseAdapter(),
        $schema,
        $input['query'],
        null,
        null,
        $input['variables'] ?? null,
        $input['operationName'] ?? null
    );
    $resultArray = [];
    $promise->then(function(ExecutionResult $result) use (&$resultArray) {
        $resultArray = $result->toArray();
    });
    echo json_encode($resultArray);
});
