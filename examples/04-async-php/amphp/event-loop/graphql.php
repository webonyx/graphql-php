<?php declare(strict_types=1);

// php -S localhost:8080 graphql.php
// curl -d '{"query": "query { product article }" }' -H "Content-Type: application/json" localhost:8080

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Amp\Loop;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\AmpPromiseAdapter;
use GraphQL\GraphQL;

$schema = require_once __DIR__ . '/../schema.php';

Loop::run(function () use ($schema): void {
    $content = file_get_contents('php://input');
    $input = '';

    if ($content !== false) {
        $input = json_decode($content, true);
    }

    $promise = GraphQL::promiseToExecute(
        new AmpPromiseAdapter(),
        $schema,
        $input['query'],
        null,
        null,
        $input['variables'] ?? null,
        $input['operationName'] ?? null
    );
    $promise->then(function (ExecutionResult $result): void {
        echo json_encode($result->toArray(), JSON_THROW_ON_ERROR);
    });
});
