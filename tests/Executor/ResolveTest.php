<?php
namespace GraphQL\Tests\Executor;

use GraphQL\ExtendableContext;
use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\ExtendableContextTrait;

require_once __DIR__ . '/TestClasses.php';

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

    private function buildExtendableContainer()
    {
      return new class implements ExtendableContext { use ExtendableContextTrait; };
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
    public function testDefaultFunctionCallsClosures()
    {
        $schema = $this->buildSchema(['type' => Type::string()]);
        $_secret = 'secretValue' . uniqid();

        $source = [
            'test' => function() use ($_secret) {
                return $_secret;
            }
        ];
        $this->assertEquals(
            ['data' => ['test' => $_secret]],
            GraphQL::execute($schema, '{ test }', $source)
        );
    }

    /**
     * @it default function passes args and context
     */
    public function testDefaultFunctionPassesArgsAndContext()
    {
        $schema = $this->buildSchema([
            'type' => Type::int(),
            'args' => [
                'addend1' => [ 'type' => Type::int() ],
            ],
        ]);

        $source = new Adder(700);

        $result = GraphQL::execute($schema, '{ test(addend1: 80) }', $source, ['addend2' => 9]);
        $this->assertEquals(['data' => ['test' => 789]], $result);
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

    /**
     * @it Correctly serializes extensions from resolveInfo.
     */
    public function testAddingExtensionsWithinResolver()
    {
        $schema = $this->buildSchema([
            'type' => Type::string(),
            'args' => [
                'aStr' => ['type' => Type::string()],
                'aInt' => ['type' => Type::int()],
            ],
            'resolve' => function ($source, $args, ExtendableContext $context, ResolveInfo $resolveInfo) {
                $context->setExtension('cache-control', ['path' => $resolveInfo->path, 'cache' => 'none']);
                return json_encode([$source, $args]);
            }
        ]);

        $this->assertEquals(
            ['data' => ['test' => '[null,[]]'], 'extensions' => ['cache-control' => ['path' => ['test'], 'cache' => 'none']]],
            GraphQL::execute($schema, '{ test }', null, $this->buildExtendableContainer())
        );
    }

    /**
     * @it Correctly returns null when attempting to retrieve an extension that has not been set.
     */
    public function testRetrievingUnsetExtensionReturnsNull()
    {
        $schema = $this->buildSchema([
            'type' => Type::string(),
            'args' => [
                'aStr' => ['type' => Type::string()],
                'aInt' => ['type' => Type::int()],
            ],
            'resolve' => function ($source, $args, ExtendableContext $context) {
                $this->assertEquals(null, $context->getExtension('cache-control'));
                return json_encode([$source, $args]);
            }
        ]);

        GraphQL::execute($schema, '{ test }', null, $this->buildExtendableContainer());
    }

    /**
     * @it Correctly enables coordination of setting extensions between resolvers.
     */
    public function testSetExtensionsAreRetrievable()
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'a' => [
                        'type' => Type::string(),
                        'resolve' => function ($source, $args, ExtendableContext $context) {
                            $actualCost = $context->getExtension('queryCost') ?: 0;
                            $actualCost += 10;
                            $context->setExtension('queryCost', $actualCost);
                            return 'foo';
                        }
                    ],
                    'b' => [
                        'type' => Type::string(),
                        'resolve' => function ($source, $args, $context) {
                            $actualCost = $context->getExtension('queryCost') ?: 0;
                            $actualCost += 10;
                            $context->setExtension('queryCost', $actualCost);
                            return 'bar';
                        }
                    ]
                ]
            ])
        ]);

        $this->assertEquals(
            ['data' => ['a' => 'foo', 'b' => 'bar'], 'extensions' => ['queryCost' => 20]],
            GraphQL::execute($schema, '{ a, b }', null, $this->buildExtendableContainer())
        );
    }
}
