<?php declare(strict_types=1);

// php graphql.php
// curl --data '{"query": "query { product article }" }' --header "Content-Type: application/json" localhost:8080

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\AmpFutureAdapter;
use GraphQL\GraphQL;
use Psr\Log\NullLogger;

use function Amp\ByteStream\buffer;

$schema = require_once __DIR__ . '/../schema.php';

$server = SocketHttpServer::createForDirectAccess(new NullLogger());
$server->expose('localhost:8080');

$server->start(
    new ClosureRequestHandler(static function (Request $request) use ($schema): Response {
        $input = json_decode(buffer($request->getBody()), true);

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
        $result = $future->await();
        assert($result instanceof ExecutionResult);

        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode($result->toArray(), JSON_THROW_ON_ERROR)
        );
    }),
    new DefaultErrorHandler()
);

Amp\trapSignal([SIGINT, SIGTERM]);

$server->stop();