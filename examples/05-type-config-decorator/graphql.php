<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Models\Author;
use App\Models\Track;
use GraphQL\GraphQL;
use GraphQL\Utils\BuildSchema;

$typeConfigDecorator = function (array $typeConfig): array {
    // For object types, the typeConfig array contains the name of the type and a closure that returns the fields of the type.
    //
    // For the Query type, it might look like this:
    // [
    //   'name': 'Query',
    //   'fields': fn () => [...],
    // ],
    switch ($typeConfig['name']) {
        case 'Query':
            $typeConfig['fields'] = function () use ($typeConfig): array {
                $fields = $typeConfig['fields']();
                $fields['tracksForHome']['resolve'] = fn (): array => Track::all();

                return $fields;
            };

            return $typeConfig;
        case 'Track':
            $typeConfig['fields'] = function () use ($typeConfig): array {
                $fields = $typeConfig['fields']();
                $fields['author']['resolve'] = fn (array $track): array => Author::find($track['authorId']);

                return $fields;
            };

            return $typeConfig;
    }

    return $typeConfig;
};
$contents = file_get_contents(__DIR__ . '/schema.graphql');
if ($contents === false) {
    throw new RuntimeException('Failed to read schema.graphql.');
}
$schema = BuildSchema::build($contents, $typeConfigDecorator);

$requestBody = file_get_contents('php://input');
if ($requestBody === false) {
    throw new RuntimeException('Failed to read php://input.');
}
$parsedBody = json_decode($requestBody, true, 10);
$queryString = $parsedBody['query'];

$result = GraphQL::executeQuery($schema, $queryString);

header('Content-Type: application/json');
echo json_encode($result);
