<?php

declare(strict_types=1);

// Test this using the following command:
// php -S localhost:8080 graphql.php

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\Error\DebugFlag;
use GraphQL\Error\FormattedError;
use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Type\QueryType;
use GraphQL\Examples\Blog\Types;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

// Replace default PHP error reporting with a better handler
ini_set('display_errors', '0');
set_error_handler(static function ($severity, $message, $file, $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// For this example, we always enable debug mode - not recommended for production
const DEBUG = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;

try {
    // Initialize our fake data source
    DataSource::init();

    // Prepare context that will be available in all field resolvers (as 3rd argument):
    $appContext          = new AppContext();
    $appContext->viewer  = DataSource::findUser(1); // simulated "currently logged-in user"
    $appContext->rootUrl = 'http://localhost:8080';
    $appContext->request = $_REQUEST;

    // Parse incoming query and variables
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input      = file_get_contents('php://input');
        $jsonString = $input === false ? '' : $input;

        $decoded = json_decode($jsonString, true);
        $data    = $decoded === false ? [] : $decoded;
    } else {
        $data = $_REQUEST;
    }

    $schema = new Schema([
        'query' => new QueryType(),
        'typeLoader' => static fn (string $name): Type => Types::byTypeName($name),
    ]);

    $result              = GraphQL::executeQuery(
        $schema,
        $data['query'] ??= /** @lang GraphQL */ '{ hello }',
        null,
        $appContext,
        $data['variables'] ?? null
    );
    $output     = $result->toArray(DEBUG);
    $httpStatus = 200;
} catch (Throwable $error) {
    $output     = [
        'errors' => [FormattedError::createFromException($error, DEBUG)],
    ];
    $httpStatus = 500;
}

header('Content-Type: application/json', true, $httpStatus);
echo json_encode($output);
