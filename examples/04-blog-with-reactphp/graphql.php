<?php
// Test this using following command
// php graphql.php
require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\Examples\Blog\Type\QueryType;
use \GraphQL\Examples\Blog\Types;
use \GraphQL\Examples\Blog\AppContext;
use \GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter;
use \GraphQL\Type\Schema;
use \GraphQL\GraphQL;
use \GraphQL\Error\FormattedError;
use \GraphQL\Error\DebugFlag;
use Psr\Http\Message\ServerRequestInterface;

// Disable default PHP error reporting - we have better one for debug mode (see below)
ini_set('display_errors', 0);
$schema  = new Schema([
    'query' => new QueryType(),
    'typeLoader' => function($name) {
        return Types::byTypeName($name, true);
    }
]);
$react = new ReactPromiseAdapter();
$server = new \React\Http\HttpServer(function (ServerRequestInterface $request) use ($schema, $react) {
    DataSource::init();
    $rawInput = (string)$request->getBody();
    $appContext = new AppContext();
    $appContext->viewer = DataSource::findUser('1'); // simulated "currently logged-in user"
    $appContext->rootUrl = '127.0.0.1:8010';
    $appContext->request = $rawInput;
    $input = json_decode($rawInput, true);
    $query = $input['query'];
    $variableValues = isset($input['variables']) ? $input['variables'] : null;
    $rootValue = ['prefix' => 'You said: '];
    $promise = GraphQL::promiseToExecute($react, $schema, $query, $rootValue, $appContext, $variableValues);
    return $promise->then(function(ExecutionResult $result) {
        $output = $result->toArray(true);
        return new \React\Http\Message\Response(
            200,
            array(
                'Content-Type' => 'text/json'
            ),
            json_encode($output)
        );
    });
});
$socket = new \React\Socket\SocketServer('127.0.0.1:8010');
$server->listen($socket);
echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
