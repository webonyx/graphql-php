<?php
namespace GraphQL\Tests\Executor;

use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class ResolveTest extends \PHPUnit_Framework_TestCase
{
    // Execute: resolve function

    private function buildSchema($testField)
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'test' => $testField
                ]
            ])
        ]);
    }

    /**
     * @it default function accesses properties
     */
    public function testDefaultFunctionAccessesProperties()
    {
        $schema = $this->buildSchema(['type' => Type::string()]);

        $source = [
            'test' => 'testValue'
        ];

        $this->assertEquals(
            ['data' => ['test' => 'testValue']],
            GraphQL::execute($schema, '{ test }', $source)
        );
    }

    /**
     * @it default function calls methods
     */
    public function testDefaultFunctionCallsMethods()
    {
        $schema = $this->buildSchema(['type' => Type::string()]);
        $_secret = 'secretValue' . uniqid();

        $source = [
            'test' => function () use ($_secret) {
                return $_secret;
            }
        ];
        $this->assertEquals(
            ['data' => ['test' => $_secret]],
            GraphQL::execute($schema, '{ test }', $source)
        );
    }

    /**
     * @it uses provided resolve function
     */
    public function testUsesProvidedResolveFunction()
    {
        $schema = $this->buildSchema([
            'type' => Type::string(),
            'args' => [
                'aStr' => ['type' => Type::string()],
                'aInt' => ['type' => Type::int()],
            ],
            'resolve' => function ($source, $args) {
                return json_encode([$source, $args]);
            }
        ]);

        $this->assertEquals(
            ['data' => ['test' => '[null,[]]']],
            GraphQL::execute($schema, '{ test }')
        );

        $this->assertEquals(
            ['data' => ['test' => '["Source!",[]]']],
            GraphQL::execute($schema, '{ test }', 'Source!')
        );

        $this->assertEquals(
            ['data' => ['test' => '["Source!",{"aStr":"String!"}]']],
            GraphQL::execute($schema, '{ test(aStr: "String!") }', 'Source!')
        );

        $this->assertEquals(
            ['data' => ['test' => '["Source!",{"aStr":"String!","aInt":-123}]']],
            GraphQL::execute($schema, '{ test(aInt: -123, aStr: "String!") }', 'Source!')
        );
    }
}