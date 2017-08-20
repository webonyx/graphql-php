<?php
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;

class ExecutorLazySchemaTest extends \PHPUnit_Framework_TestCase
{
    public $SomeScalarType;

    public $SomeObjectType;

    public $OtherObjectType;

    public $DeeperObjectType;

    public $SomeUnionType;

    public $SomeInterfaceType;

    public $SomeEnumType;

    public $SomeInputObjectType;

    public $QueryType;

    public $calls = [];

    public $loadedTypes = [];

    public function testWarnsAboutSlowIsTypeOfForLazySchema()
    {
        // isTypeOf used to resolve runtime type for Interface
        $petType = new InterfaceType([
            'name' => 'Pet',
            'fields' => function() {
                return [
                    'name' => ['type' => Type::string()]
                ];
            }
        ]);

        // Added to interface type when defined
        $dogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => function($obj) { return $obj instanceof Dog; },
            'fields' => function() {
                return [
                    'name' => ['type' => Type::string()],
                    'woofs' => ['type' => Type::boolean()]
                ];
            }
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf' => function ($obj) {
                return $obj instanceof Cat;
            },
            'fields' => function() {
                return [
                    'name' => ['type' => Type::string()],
                    'meows' => ['type' => Type::boolean()],
                ];
            }
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($petType),
                        'resolve' => function () {
                            return [new Dog('Odie', true), new Cat('Garfield', false)];
                        }
                    ]
                ]
            ]),
            'types' => [$catType, $dogType],
            'typeLoader' => function($name) use ($dogType, $petType, $catType) {
                switch ($name) {
                    case 'Dog':
                        return $dogType;
                    case 'Pet':
                        return $petType;
                    case 'Cat':
                        return $catType;
                }
            }
        ]);

        $query = '{
          pets {
            name
            ... on Dog {
              woofs
            }
            ... on Cat {
              meows
            }
          }
        }';

        $expected = new ExecutionResult([
            'pets' => [
                ['name' => 'Odie', 'woofs' => true],
                ['name' => 'Garfield', 'meows' => false]
            ],
        ]);

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        $result = Executor::execute($schema, Parser::parse($query));
        $this->assertEquals($expected, $result);

        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);
        $result = Executor::execute($schema, Parser::parse($query));
        $this->assertEquals(1, count($result->errors));
        $this->assertInstanceOf('PHPUnit_Framework_Error_Warning', $result->errors[0]->getPrevious());

        $this->assertEquals(
            'GraphQL Interface Type `Pet` returned `null` from it`s `resolveType` function for value: instance of '.
            'GraphQL\Tests\Executor\Dog. Switching to slow resolution method using `isTypeOf` of all possible '.
            'implementations. It requires full schema scan and degrades query performance significantly.  '.
            'Make sure your `resolveType` always returns valid implementation or throws.',
            $result->errors[0]->getMessage());
    }

    public function testHintsOnConflictingTypeInstancesInDefinitions()
    {
        $calls = [];
        $typeLoader = function($name) use (&$calls) {
            $calls[] = $name;
            switch ($name) {
                case 'Test':
                    return new ObjectType([
                        'name' => 'Test',
                        'fields' => function() {
                            return [
                                'test' => Type::string(),
                            ];
                        }
                    ]);
                default:
                    return null;
            }
        };
        $query = new ObjectType([
            'name' => 'Query',
            'fields' => function() use ($typeLoader) {
                return [
                    'test' => $typeLoader('Test')
                ];
            }
        ]);
        $schema = new Schema([
            'query' => $query,
            'typeLoader' => $typeLoader
        ]);

        $query = '
            {
                test {
                    test
                }
            }
        ';

        $this->assertEquals([], $calls);
        $result = Executor::execute($schema, Parser::parse($query), ['test' => ['test' => 'value']]);
        $this->assertEquals(['Test', 'Test'], $calls);

        $this->assertEquals(
            'Schema must contain unique named types but contains multiple types named "Test". '.
            'Make sure that type loader returns the same instance as defined in Query.test '.
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).',
            $result->errors[0]->getMessage()
        );
        $this->assertInstanceOf(
            InvariantViolation::class,
            $result->errors[0]->getPrevious()
        );
    }

    public function testSimpleQuery()
    {
        $schema = new Schema([
            'query' => $this->loadType('Query'),
            'typeLoader' => function($name) {
                return $this->loadType($name, true);
            }
        ]);

        $query = '{ object { string } }';
        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            ['object' => ['string' => 'test']]
        );

        $expected = [
            'data' => ['object' => ['string' => 'test']],
        ];
        $expectedExecutorCalls = [
            'Query.fields',
            'SomeObject',
            'SomeObject.fields'
        ];
        $this->assertEquals($expected, $result->toArray(true));
        $this->assertEquals($expectedExecutorCalls, $this->calls);
    }

    public function testDeepQuery()
    {
        $schema = new Schema([
            'query' => $this->loadType('Query'),
            'typeLoader' => function($name) {
                return $this->loadType($name, true);
            }
        ]);

        $query = '{ object { object { object { string } } } }';
        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            ['object' => ['object' => ['object' => ['string' => 'test']]]]
        );

        $expected = [
            'data' => ['object' => ['object' => ['object' => ['string' => 'test']]]]
        ];
        $expectedLoadedTypes = [
            'Query' => true,
            'SomeObject' => true,
            'OtherObject' => true
        ];

        $this->assertEquals($expected, $result->toArray(true));
        $this->assertEquals($expectedLoadedTypes, $this->loadedTypes);

        $expectedExecutorCalls = [
            'Query.fields',
            'SomeObject',
            'SomeObject.fields'
        ];
        $this->assertEquals($expectedExecutorCalls, $this->calls);
    }

    public function testResolveUnion()
    {
        $schema = new Schema([
            'query' => $this->loadType('Query'),
            'typeLoader' => function($name) {
                return $this->loadType($name, true);
            }
        ]);

        $query = '
            { 
                other { 
                    union {
                        scalar 
                    } 
                } 
            }
        ';
        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            ['other' => ['union' => ['scalar' => 'test']]]
        );

        $expected = [
            'data' => ['other' => ['union' => ['scalar' => 'test']]],
        ];
        $expectedLoadedTypes = [
            'Query' => true,
            'SomeObject' => true,
            'OtherObject' => true,
            'SomeUnion' => true,
            'SomeInterface' => true,
            'DeeperObject' => true,
            'SomeScalar' => true,
        ];

        $this->assertEquals($expected, $result->toArray(true));
        $this->assertEquals($expectedLoadedTypes, $this->loadedTypes);

        $expectedCalls = [
            'Query.fields',
            'OtherObject',
            'OtherObject.fields',
            'SomeUnion',
            'SomeUnion.resolveType',
            'SomeUnion.types',
            'DeeperObject',
            'SomeScalar',
        ];
        $this->assertEquals($expectedCalls, $this->calls);
    }

    public function loadType($name, $isExecutorCall = false)
    {
        if ($isExecutorCall) {
            $this->calls[] = $name;
        }
        $this->loadedTypes[$name] = true;

        switch ($name) {
            case 'Query':
                return $this->QueryType ?: $this->QueryType = new ObjectType([
                    'name' => 'Query',
                    'fields' => function() {
                        $this->calls[] = 'Query.fields';
                        return [
                            'object' => ['type' => $this->loadType('SomeObject')],
                            'other' => ['type' => $this->loadType('OtherObject')],
                        ];
                    }
                ]);
            case 'SomeObject':
                return $this->SomeObjectType ?: $this->SomeObjectType = new ObjectType([
                    'name' => 'SomeObject',
                    'fields' => function() {
                        $this->calls[] = 'SomeObject.fields';
                        return [
                            'string' => ['type' => Type::string()],
                            'object' => ['type' => $this->SomeObjectType]
                        ];
                    },
                    'interfaces' => function() {
                        $this->calls[] = 'SomeObject.interfaces';
                        return [
                            $this->loadType('SomeInterface')
                        ];
                    }
                ]);
            case 'OtherObject':
                return $this->OtherObjectType ?: $this->OtherObjectType =  new ObjectType([
                    'name' => 'OtherObject',
                    'fields' => function() {
                        $this->calls[] = 'OtherObject.fields';
                        return [
                            'union' => ['type' => $this->loadType('SomeUnion')],
                            'iface' => ['type' => Type::nonNull($this->loadType('SomeInterface'))],
                        ];
                    }
                ]);
            case 'DeeperObject':
                return $this->DeeperObjectType ?: $this->DeeperObjectType = new ObjectType([
                    'name' => 'DeeperObject',
                    'fields' => function() {
                        return [
                            'scalar' => ['type' => $this->loadType('SomeScalar')],
                        ];
                    }
                ]);
            case 'SomeScalar';
                return $this->SomeScalarType ?: $this->SomeScalarType = new CustomScalarType([
                    'name' => 'SomeScalar',
                    'serialize' => function($value) {return $value;},
                    'parseValue' => function($value) {return $value;},
                    'parseLiteral' => function() {}
                ]);
            case 'SomeUnion':
                return $this->SomeUnionType ?: $this->SomeUnionType = new UnionType([
                    'name' => 'SomeUnion',
                    'resolveType' => function() {
                        $this->calls[] = 'SomeUnion.resolveType';
                        return $this->loadType('DeeperObject');
                    },
                    'types' => function() {
                        $this->calls[] = 'SomeUnion.types';
                        return [ $this->loadType('DeeperObject') ];
                    }
                ]);
            case 'SomeInterface':
                return $this->SomeInterfaceType ?: $this->SomeInterfaceType = new InterfaceType([
                    'name' => 'SomeInterface',
                    'resolveType' => function() {
                        $this->calls[] = 'SomeInterface.resolveType';
                        return $this->loadType('SomeObject');
                    },
                    'fields' => function() {
                        $this->calls[] = 'SomeInterface.fields';
                        return  [
                            'string' => ['type' => Type::string() ]
                        ];
                    }
                ]);
            default:
                return null;
        }
    }
}
