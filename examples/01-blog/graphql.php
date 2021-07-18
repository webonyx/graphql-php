<?php

declare(strict_types=1);

// Test this using the following command:
// php -S localhost:8080 graphql.php

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Type\QueryType;
use GraphQL\Examples\Blog\Types;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

try {
    // Initialize our fake data source
    DataSource::init();

    // Prepare context that will be available in all field resolvers (as 3rd argument):
    $appContext          = new AppContext();
    $appContext->viewer  = DataSource::findUser(1); // simulated "currently logged-in user"
    $appContext->rootUrl = 'http://localhost:8080';
    $appContext->request = $_REQUEST;

    $schema = new Schema([
        'query' => new QueryType(),
        'typeLoader' => static fn (string $name): Type => Types::byTypeName($name),
    ]);

    // See docs on server options:
    // https://webonyx.github.io/graphql-php/executing-queries/#server-configuration-options
    $server = new StandardServer(['schema' => $schema]);

    $server->handleRequest();
} catch (Throwable $error) {
    StandardServer::send500Error($error);
}
