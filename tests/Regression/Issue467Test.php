<?php

declare(strict_types=1);

namespace GraphQL\Tests\Regression;

use GraphQL\GraphQL;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;

/**
 * @see https://github.com/webonyx/graphql-php/issues/467
 */
class Issue467Test extends TestCase
{
    public function testInputObjectValidation()
    {
        $schemaStr = '
input MsgInput {
  msg: String
}

type Query {
  echo(msg: MsgInput): String
}

schema {
    query: Query
}
';

        $query     = '
query echo ($msg: MsgInput) {
          echo (msg: $msg)
}';
        $variables = ['msg' => ['my message']];

        $schema = BuildSchema::build($schemaStr);
        $result = GraphQL::executeQuery($schema, $query, null, null, $variables);

        $expectedError = 'Variable "$msg" got invalid value ["my message"]; Field "0" is not defined by type MsgInput.';
        self::assertCount(1, $result->errors);
        self::assertEquals($expectedError, $result->errors[0]->getMessage());
    }
}
