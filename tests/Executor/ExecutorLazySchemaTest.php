<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Tests\Executor\TestClasses\Cat;
use GraphQL\Tests\Executor\TestClasses\Dog;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Error\Error;
use PHPUnit\Framework\TestCase;
use function count;

class ExecutorLazySchemaTest extends TestCase
{
    /** @var ScalarType */
    public $someScalarType;

    /** @var ObjectType */
    public $someObjectType;

    /** @var ObjectType */
    public $otherObjectType;

    /** @var ObjectType */
    public $deeperObjectType;

    /** @var UnionType */
    public $someUnionType;

    /** @var InterfaceType */
    public $someInterfaceType;

    /** @var EnumType */
    public $someEnumType;

    /** @var InputObjectType */
    public $someInputObjectType;

    /** @var ObjectType */
    public $queryType;

    /** @var string[] */
    public $calls = [];

    /** @var bool[] */
    public $loadedTypes = [];

    public function testWarnsAboutSlowIsTypeOfForLazySchema() : void
    {
        // isTypeOf used to resolve runtime type for Interface
        $petType = new InterfaceType([
            'name'   => 'Pet',
            'fields' => static function () : array {
                return [
                    'name' => ['type' => Type::string()],
                ];
            },
        ]);

        // Added to interface type when defined
        $dogType = new ObjectType([
            'name'       => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf'   => static function ($obj) : bool {
                return $obj instanceof Dog;
            },
            'fields'     => static function () : array {
                return [
                    'name'  => ['type' => Type::string()],
                    'woofs' => ['type' => Type::boolean()],
                ];
            },
        ]);

        $catType = new ObjectType([
            'name'       => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf'   => static function ($obj) : bool {
                return $obj instanceof Cat;
            },
            'fields'     => static function () : array {
                return [
                    'name'  => ['type' => Type::string()],
                    'meows' => ['type' => Type::boolean()],
                ];
            },
        ]);

        $schema = new Schema([
            'query'      => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'pets' => [
                        'type'    => Type::listOf($petType),
                        'resolve' => static function () : array {
                            return [new Dog('Odie', true), new Cat('Garfield', false)];
                        },
                    ],
                ],
            ]),
            'types'      => [$catType, $dogType],
            'typeLoader' => static function ($name) use ($dogType, $petType, $catType) {
                switch ($name) {
                    case 'Dog':
                        return $dogType;
                    case 'Pet':
                        return $petType;
                    case 'Cat':
                        return $catType;
                }
            },
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
                ['name' => 'Garfield', 'meows' => false],
            ],
        ]);

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        $result = Executor::execute($schema, Parser::parse($query));
        self::assertEquals($expected, $result);

        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);
        $result = Executor::execute($schema, Parser::parse($query));
        self::assertEquals(1, count($result->errors));
        self::assertInstanceOf(Error::class, $result->errors[0]->getPrevious());

        self::assertEquals(
            'GraphQL Interface Type `Pet` returned `null` from its `resolveType` function for value: instance of ' .
            'GraphQL\Tests\Executor\TestClasses\Dog. Switching to slow resolution method using `isTypeOf` of all possible ' .
            'implementations. It requires full schema scan and degrades query performance significantly.  ' .
            'Make sure your `resolveType` always returns valid implementation or throws.',
            $result->errors[0]->getMessage()
        );
    }

    public function testHintsOnConflictingTypeInstancesInDefinitions() : void
    {
        $calls      = [];
        $typeLoader = static function ($name) use (&$calls) {
            $calls[] = $name;
            switch ($name) {
                case 'Test':
                    return new ObjectType([
                        'name'   => 'Test',
                        'fields' => static function () : array {
                            return [
                                'test' => Type::string(),
                            ];
                        },
                    ]);
                default:
                    return null;
            }
        };

        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => static function () use ($typeLoader) : array {
                return [
                    'test' => $typeLoader('Test'),
                ];
            },
        ]);

        $schema = new Schema([
            'query'      => $query,
            'typeLoader' => $typeLoader,
        ]);

        $query = '
            {
                test {
                    test
                }
            }
        ';

        self::assertEquals([], $calls);
        $result = Executor::execute($schema, Parser::parse($query), ['test' => ['test' => 'value']]);
        self::assertEquals(['Test', 'Test'], $calls);

        self::assertEquals(
            'Schema must contain unique named types but contains multiple types named "Test". ' .
            'Make sure that type loader returns the same instance as defined in Query.test ' .
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).',
            $result->errors[0]->getMessage()
        );
        self::assertInstanceOf(
            InvariantViolation::class,
            $result->errors[0]->getPrevious()
        );
    }

    public function testSimpleQuery() : void
    {
        $schema = new Schema([
            'query'      => $this->loadType('Query'),
            'typeLoader' => function ($name) {
                return $this->loadType($name, true);
            },
        ]);

        $query  = '{ object { string } }';
        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            ['object' => ['string' => 'test']]
        );

        $expected              = [
            'data' => ['object' => ['string' => 'test']],
        ];
        $expectedExecutorCalls = [
            'Query.fields',
            'SomeObject',
            'SomeObject.fields',
        ];
        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
        self::assertEquals($expectedExecutorCalls, $this->calls);
    }

    public function loadType($name, $isExecutorCall = false)
    {
        if ($isExecutorCall) {
            $this->calls[] = $name;
        }
        $this->loadedTypes[$name] = true;

        switch ($name) {
            case 'Query':
                return $this->queryType ?? $this->queryType = new ObjectType([
                    'name'   => 'Query',
                    'fields' => function () : array {
                        $this->calls[] = 'Query.fields';

                        return [
                            'object' => ['type' => $this->loadType('SomeObject')],
                            'other'  => ['type' => $this->loadType('OtherObject')],
                        ];
                    },
                ]);
            case 'SomeObject':
                return $this->someObjectType ?? $this->someObjectType = new ObjectType([
                    'name'       => 'SomeObject',
                    'fields'     => function () : array {
                        $this->calls[] = 'SomeObject.fields';

                        return [
                            'string' => ['type' => Type::string()],
                            'object' => ['type' => $this->someObjectType],
                        ];
                    },
                    'interfaces' => function () : array {
                        $this->calls[] = 'SomeObject.interfaces';

                        return [
                            $this->loadType('SomeInterface'),
                        ];
                    },
                ]);
            case 'OtherObject':
                return $this->otherObjectType ?? $this->otherObjectType = new ObjectType([
                    'name'   => 'OtherObject',
                    'fields' => function () : array {
                        $this->calls[] = 'OtherObject.fields';

                        return [
                            'union' => ['type' => $this->loadType('SomeUnion')],
                            'iface' => ['type' => Type::nonNull($this->loadType('SomeInterface'))],
                        ];
                    },
                ]);
            case 'DeeperObject':
                return $this->deeperObjectType ?? $this->deeperObjectType = new ObjectType([
                    'name'   => 'DeeperObject',
                    'fields' => function () : array {
                        return [
                            'scalar' => ['type' => $this->loadType('SomeScalar')],
                        ];
                    },
                ]);
            case 'SomeScalar':
                return $this->someScalarType ?? $this->someScalarType = new CustomScalarType([
                    'name'         => 'SomeScalar',
                    'serialize'    => static function ($value) {
                        return $value;
                    },
                    'parseValue'   => static function ($value) {
                        return $value;
                    },
                    'parseLiteral' => static function () : void {
                    },
                ]);
            case 'SomeUnion':
                return $this->someUnionType ?? $this->someUnionType = new UnionType([
                    'name'        => 'SomeUnion',
                    'resolveType' => function () {
                        $this->calls[] = 'SomeUnion.resolveType';

                        return $this->loadType('DeeperObject');
                    },
                    'types'       => function () : array {
                        $this->calls[] = 'SomeUnion.types';

                        return [$this->loadType('DeeperObject')];
                    },
                ]);
            case 'SomeInterface':
                return $this->someInterfaceType ?? $this->someInterfaceType = new InterfaceType([
                    'name'        => 'SomeInterface',
                    'resolveType' => function () {
                        $this->calls[] = 'SomeInterface.resolveType';

                        return $this->loadType('SomeObject');
                    },
                    'fields'      => function () : array {
                        $this->calls[] = 'SomeInterface.fields';

                        return [
                            'string' => ['type' => Type::string()],
                        ];
                    },
                ]);
            default:
                return null;
        }
    }

    public function testDeepQuery() : void
    {
        $schema = new Schema([
            'query'      => $this->loadType('Query'),
            'typeLoader' => function ($name) {
                return $this->loadType($name, true);
            },
        ]);

        $query  = '{ object { object { object { string } } } }';
        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            ['object' => ['object' => ['object' => ['string' => 'test']]]]
        );

        $expected            = [
            'data' => ['object' => ['object' => ['object' => ['string' => 'test']]]],
        ];
        $expectedLoadedTypes = [
            'Query'       => true,
            'SomeObject'  => true,
            'OtherObject' => true,
        ];

        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
        self::assertEquals($expectedLoadedTypes, $this->loadedTypes);

        $expectedExecutorCalls = [
            'Query.fields',
            'SomeObject',
            'SomeObject.fields',
        ];
        self::assertEquals($expectedExecutorCalls, $this->calls);
    }

    public function testResolveUnion() : void
    {
        $schema = new Schema([
            'query'      => $this->loadType('Query'),
            'typeLoader' => function ($name) {
                return $this->loadType($name, true);
            },
        ]);

        $query  = '
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

        $expected            = [
            'data' => ['other' => ['union' => ['scalar' => 'test']]],
        ];
        $expectedLoadedTypes = [
            'Query'         => true,
            'SomeObject'    => true,
            'OtherObject'   => true,
            'SomeUnion'     => true,
            'SomeInterface' => true,
            'DeeperObject'  => true,
            'SomeScalar'    => true,
        ];

        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
        self::assertEquals($expectedLoadedTypes, $this->loadedTypes);

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
        self::assertEquals($expectedCalls, $this->calls);
    }
}
