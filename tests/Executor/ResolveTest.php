<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\GraphQL;
use GraphQL\Tests\Executor\TestClasses\Adder;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use function json_encode;
use function uniqid;

class ResolveTest extends TestCase
{
    // Execute: resolve function

    /**
     * @see it('default function accesses properties')
     */
    public function testDefaultFunctionAccessesProperties() : void
    {
        $schema = $this->buildSchema(['type' => Type::string()]);

        $source = ['test' => 'testValue'];

        self::assertEquals(
            ['data' => ['test' => 'testValue']],
            GraphQL::executeQuery($schema, '{ test }', $source)->toArray()
        );
    }

    private function buildSchema($testField)
    {
        return new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => ['test' => $testField],
            ]),
        ]);
    }

    /**
     * @see it('default function calls methods')
     */
    public function testDefaultFunctionCallsClosures() : void
    {
        $schema  = $this->buildSchema(['type' => Type::string()]);
        $_secret = 'secretValue' . uniqid();

        $source = [
            'test' => static function () use ($_secret) {
                return $_secret;
            },
        ];
        self::assertEquals(
            ['data' => ['test' => $_secret]],
            GraphQL::executeQuery($schema, '{ test }', $source)->toArray()
        );
    }

    /**
     * @see it('default function passes args and context')
     */
    public function testDefaultFunctionPassesArgsAndContext() : void
    {
        $schema = $this->buildSchema([
            'type' => Type::int(),
            'args' => [
                'addend1' => ['type' => Type::int()],
            ],
        ]);

        $source = new Adder(700);

        $result = GraphQL::executeQuery($schema, '{ test(addend1: 80) }', $source, ['addend2' => 9])->toArray();
        self::assertEquals(['data' => ['test' => 789]], $result);
    }

    /**
     * @see it('uses provided resolve function')
     */
    public function testUsesProvidedResolveFunction() : void
    {
        $schema = $this->buildSchema([
            'type'    => Type::string(),
            'args'    => [
                'aStr' => ['type' => Type::string()],
                'aInt' => ['type' => Type::int()],
            ],
            'resolve' => static function ($source, $args) {
                return json_encode([$source, $args]);
            },
        ]);

        self::assertEquals(
            ['data' => ['test' => '[null,[]]']],
            GraphQL::executeQuery($schema, '{ test }')->toArray()
        );

        self::assertEquals(
            ['data' => ['test' => '["Source!",[]]']],
            GraphQL::executeQuery($schema, '{ test }', 'Source!')->toArray()
        );

        self::assertEquals(
            ['data' => ['test' => '["Source!",{"aStr":"String!"}]']],
            GraphQL::executeQuery($schema, '{ test(aStr: "String!") }', 'Source!')->toArray()
        );

        self::assertEquals(
            ['data' => ['test' => '["Source!",{"aStr":"String!","aInt":-123}]']],
            GraphQL::executeQuery($schema, '{ test(aInt: -123, aStr: "String!") }', 'Source!')->toArray()
        );
    }
}
