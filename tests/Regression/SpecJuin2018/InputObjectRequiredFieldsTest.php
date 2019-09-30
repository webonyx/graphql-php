<?php

declare(strict_types=1);

namespace GraphQL\Tests\Regression\SpecJuin2018;

use GraphQL\GraphQL;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;

/**
 * @see https://graphql.github.io/graphql-spec/June2018/#sec-Input-Object-Required-Fields
 */
class InputObjectRequiredFieldsTest extends TestCase
{
    public function testInputObjectNotRequiredFieldWithDefaultValueValidation()
    {
        $schemaStr = '
            input FooQueryInput {
                nonNullWithDefaultValue: String! = "foo"
            }

            type Query {
                foo(input: FooQueryInput!): Boolean
            }

            schema {
                query: Query
            }
        ';

        $query = '
            {
                foo(input: {})
            }
        ';

        $schema = BuildSchema::build($schemaStr);
        $result = GraphQL::executeQuery($schema, $query, null, null, []);

        $this->assertCount(0, $result->errors, 'An input field is required if it has a non‐null type and does not have a default value. Otherwise, the input object field is optional.');
    }
}
