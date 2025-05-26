<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Track;
use App\Models\Author;
use GraphQL\GraphQL;
use GraphQL\Utils\BuildSchema;

ini_set('display_errors', 1);

$typeConfigDecorator = function($typeConfig) {
  /*
    The hypothetical shape of $typeConfig in iterations :
  
    [
    // Each iteration, the name property gets the name of a type from our schema like : Query, Track and Author
    'name': "Query",
    'fields': fn() => {}
    ]
  */
  switch($typeConfig['name']) {
    case "Query":
      $typeConfig['fields'] = function() use ($typeConfig) {
        $fields = $typeConfig['fields']();
        $fields['tracksForHome']['resolve'] = fn() => Track::all();
        return $fields;
      };
    break;
    case "Track":
      $typeConfig['fields'] = function() use ($typeConfig) {
        $fields = $typeConfig['fields']();
        $fields['author']['resolve'] = fn($track) => Author::find($track['authorId']);
        return $fields;
      };
    break;
  }
  return $typeConfig;
};
$contents = file_get_contents(__DIR__ . '/schema.graphql');
$schema = BuildSchema::build($contents, $typeConfigDecorator);

$requestBody = file_get_contents('php://input');
$parsedBody = json_decode($requestBody, true, 10);
$queryString = $parsedBody['query'];

$result = GraphQL::executeQuery($schema, $queryString);

header('Content-Type: application/json');
echo json_encode($result);
