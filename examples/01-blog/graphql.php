<?php

declare(strict_types=1);

// Test this using following command
// php -S localhost:8080 ./graphql.php
require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\Error\DebugFlag;
use GraphQL\Error\FormattedError;
use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Type\QueryType;
use GraphQL\Examples\Blog\Types;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;

// Disable default PHP error reporting - we have better one for debug mode (see below)
ini_set('display_errors', 0);

$debug = DebugFlag::NONE;
if (! empty($_GET['debug'])) {
    set_error_handler(static function ($severity, $message, $file, $line): void {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
}

try {
    // Initialize our fake data source
    DataSource::init();

    // Prepare context that will be available in all field resolvers (as 3rd argument):
    $appContext          = new AppContext();
    $appContext->viewer  = DataSource::findUser('1'); // simulated "currently logged-in user"
    $appContext->rootUrl = 'http://localhost:8080';
    $appContext->request = $_REQUEST;

    // Parse incoming query and variables
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $raw  = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true) ?: [];
    } else {
        $data = $_REQUEST;
    }

    $data += ['query' => null, 'variables' => null];

    if ($data['query'] === null) {
        $data['query'] = '{hello}';
    }

    // GraphQL schema to be passed to query executor:
    $schema = new Schema([
        'query' => new QueryType(),
        'typeLoader' => static function ($name) {
            return Types::byTypeName($name, true);
        },
    ]);

    $result     = GraphQL::executeQuery(
        $schema,
        $data['query'],
        null,
        $appContext,
        (array) $data['variables']
    );
    $output     = $result->toArray($debug);
    $httpStatus = 200;
} catch (Throwable $error) {
    $httpStatus       = 500;
    $output['errors'] = [FormattedError::createFromException($error, $debug)];
}

header('Content-Type: application/json', true, $httpStatus);
echo json_encode($output);
