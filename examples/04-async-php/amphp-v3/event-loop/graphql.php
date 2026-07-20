<?php declare(strict_types=1);

// php -S localhost:8080 graphql.php
// curl --data '{"query": "query { product article }" }' --header "Content-Type: application/json" localhost:8080

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Amp\Future;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\AmpFutureAdapter;
use GraphQL\GraphQL;

$schema = require_once __DIR__ . '/../../amphp-v3/schema.php';

$content = file_get_contents('php://input');
$input = [];

if ($content !== false) {
    $input = json_decode($content, true);
}

$adapter = new AmpFutureAdapter();

$promise = GraphQL::promiseToExecute(
    $adapter,
    $schema,
    $input['query'],
    null,
    null,
    $input['variables'] ?? null,
    $input['operationName'] ?? null
);

$future = $promise->adoptedPromise;
assert($future instanceof Future);

$result = $future->await();
assert($result instanceof ExecutionResult);

echo json_encode($result->toArray(), JSON_THROW_ON_ERROR);
