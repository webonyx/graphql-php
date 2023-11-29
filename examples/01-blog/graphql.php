<?php declare(strict_types=1);

// Run local test server
// php -S localhost:8080 graphql.php

// Try query
// curl -d '{"query": "query { hello }" }' -H "Content-Type: application/json" http://localhost:8080

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Type\QueryType;
use GraphQL\Examples\Blog\Types;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;

// Initialize our fake data source
DataSource::init();

// See docs on schema options:
// https://webonyx.github.io/graphql-php/schema-definition/#configuration-options
$schema = new Schema(
    (new SchemaConfig())
    ->setQuery(new QueryType())
    ->setTypeLoader([Types::class, 'load'])
);

// Prepare context that will be available in all field resolvers (as 3rd argument):
$appContext = new AppContext();
$currentlyLoggedInUser = DataSource::findUser(1);
assert($currentlyLoggedInUser !== null);
$appContext->viewer = $currentlyLoggedInUser;
$appContext->rootUrl = 'http://localhost:8080';
$appContext->request = $_REQUEST;

// See docs on server options:
// https://webonyx.github.io/graphql-php/executing-queries/#server-configuration-options
$server = new StandardServer([
    'schema' => $schema,
    'context' => $appContext,
]);

$server->handleRequest();
