<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
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
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class ExecutorLazySchemaTest extends TestCase
{
    public ScalarType $someScalarType;

    public ObjectType $someObjectType;

    public ObjectType $otherObjectType;

    public ObjectType $deeperObjectType;

    public UnionType $someUnionType;

    public InterfaceType $someInterfaceType;

    public EnumType $someEnumType;

    public InputObjectType $someInputObjectType;

    public ObjectType $queryType;

    /** @var array<int, string> */
    public array $calls = [];

    /** @var array<string, true> */
    public array $loadedTypes = [];

    public function testWarnsAboutSlowIsTypeOfForLazySchema(): void
    {
        // isTypeOf used to resolve runtime type for Interface
        $petType = new InterfaceType([
            'name' => 'Pet',
            'fields' => static fn (): array => [
                'name' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        // Added to interface type when defined
        $dogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => static fn ($obj): bool => $obj instanceof Dog,
            'fields' => static fn (): array => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf' => static fn ($obj): bool => $obj instanceof Cat,
            'fields' => static fn (): array => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($petType),
                        'resolve' => static fn (): array => [
                            new Dog('Odie', true),
                            new Cat('Garfield', false),
                        ],
                    ],
                ],
            ]),
            'types' => [$catType, $dogType],
            'typeLoader' => static function ($name) use ($dogType, $petType, $catType): ?Type {
                switch ($name) {
                    case 'Dog': return $dogType;
                    case 'Pet': return $petType;
                    case 'Cat': return $catType;
                    default: return null;
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
        self::assertCount(1, $result->errors);
        $error = $result->errors[0] ?? null;
        self::assertInstanceOf(Error::class, $error);
        self::assertSame(
            'GraphQL Interface Type `Pet` returned `null` from its `resolveType` function for value: instance of GraphQL\Tests\Executor\TestClasses\Dog. Switching to slow resolution method using `isTypeOf` of all possible implementations. It requires full schema scan and degrades query performance significantly. Make sure your `resolveType` function always returns a valid implementation or throws.',
            $error->getMessage()
        );
    }

    public function testHintsOnConflictingTypeInstancesInDefinitions(): void
    {
        $calls = [];
        $typeLoader = static function ($name) use (&$calls): ?ObjectType {
            $calls[] = $name;
            switch ($name) {
                case 'Test': return new ObjectType([
                    'name' => 'Test',
                    'fields' => static fn (): array => [
                        'test' => Type::string(),
                    ],
                ]);
                default: return null;
            }
        };

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => static fn (): array => [
                'test' => $typeLoader('Test'),
            ],
        ]);

        $schema = new Schema([
            'query' => $query,
            'typeLoader' => $typeLoader,
        ]);

        $query = '
            {
                test {
                    test
                }
            }
        ';

        self::assertSame([], $calls);
        $result = Executor::execute($schema, Parser::parse($query), ['test' => ['test' => 'value']]);
        self::assertSame(['Test', 'Test'], $calls); // @phpstan-ignore-line side-effects

        $error = $result->errors[0] ?? null;
        self::assertInstanceOf(Error::class, $error);
        self::assertStringContainsString(
            'Found duplicate type in schema at Query.test: Test. Ensure the type loader returns the same instance. See https://webonyx.github.io/graphql-php/type-definitions/#type-registry.',
            $error->getMessage()
        );
    }

    public function testSimpleQuery(): void
    {
        $Query = $this->loadType('Query');
        assert($Query instanceof ObjectType);

        $schema = new Schema([
            'query' => $Query,
            'typeLoader' => fn (string $name): ?Type => $this->loadType($name, true),
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
            'SomeObject.fields',
        ];
        self::assertSame($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
        self::assertSame($expectedExecutorCalls, $this->calls);
    }

    /**
     * @throws InvariantViolation
     *
     * @return (Type&NamedType)|null
     */
    public function loadType(string $name, bool $isExecutorCall = false): ?Type
    {
        if ($isExecutorCall) {
            $this->calls[] = $name;
        }

        $this->loadedTypes[$name] = true;

        switch ($name) {
            case 'Query':
                return $this->queryType ??= new ObjectType([
                    'name' => 'Query',
                    'fields' => function (): array {
                        $this->calls[] = 'Query.fields';

                        return [
                            'object' => ['type' => $this->loadType('SomeObject')],
                            'other' => ['type' => $this->loadType('OtherObject')],
                        ];
                    },
                ]);

            case 'SomeObject':
                return $this->someObjectType ??= new ObjectType([
                    'name' => 'SomeObject',
                    'fields' => function (): array {
                        $this->calls[] = 'SomeObject.fields';

                        return [
                            'string' => ['type' => Type::string()],
                            'object' => ['type' => $this->someObjectType],
                        ];
                    },
                    'interfaces' => function (): array {
                        $this->calls[] = 'SomeObject.interfaces';

                        $someInterface = $this->loadType('SomeInterface');
                        assert($someInterface instanceof InterfaceType);

                        return [
                            $someInterface,
                        ];
                    },
                ]);

            case 'OtherObject':
                return $this->otherObjectType ??= new ObjectType([
                    'name' => 'OtherObject',
                    'fields' => function (): array {
                        $this->calls[] = 'OtherObject.fields';

                        $someUnion = $this->loadType('SomeUnion');
                        assert($someUnion instanceof UnionType);

                        $someInterface = $this->loadType('SomeInterface');
                        assert($someInterface instanceof InterfaceType);

                        return [
                            'union' => ['type' => $someUnion],
                            'iface' => ['type' => Type::nonNull($someInterface)],
                        ];
                    },
                ]);

            case 'DeeperObject':
                return $this->deeperObjectType ??= new ObjectType([
                    'name' => 'DeeperObject',
                    'fields' => fn (): array => [
                        'scalar' => ['type' => $this->loadType('SomeScalar')],
                    ],
                ]);

            case 'SomeScalar':
                return $this->someScalarType ??= new CustomScalarType([
                    'name' => 'SomeScalar',
                    'serialize' => static fn ($value) => $value,
                    'parseValue' => static fn ($value) => $value,
                    'parseLiteral' => static fn () => null,
                ]);

            case 'SomeUnion':
                return $this->someUnionType ??= new UnionType([
                    'name' => 'SomeUnion',
                    'resolveType' => function () {
                        $this->calls[] = 'SomeUnion.resolveType';

                        $deeperObject = $this->loadType('DeeperObject');
                        assert($deeperObject instanceof ObjectType);

                        return $deeperObject;
                    },
                    'types' => function (): array {
                        $this->calls[] = 'SomeUnion.types';

                        $deeperObject = $this->loadType('DeeperObject');
                        assert($deeperObject instanceof ObjectType);

                        return [$deeperObject];
                    },
                ]);

            case 'SomeInterface':
                return $this->someInterfaceType ??= new InterfaceType([
                    'name' => 'SomeInterface',
                    'resolveType' => function () {
                        $this->calls[] = 'SomeInterface.resolveType';

                        $someObject = $this->loadType('SomeObject');
                        assert($someObject instanceof ObjectType);

                        return $someObject;
                    },
                    'fields' => function (): array {
                        $this->calls[] = 'SomeInterface.fields';

                        return [
                            'string' => [
                                'type' => Type::string(),
                            ],
                        ];
                    },
                ]);

            default:
                return null;
        }
    }

    public function testDeepQuery(): void
    {
        $Query = $this->loadType('Query');
        assert($Query instanceof ObjectType);

        $schema = new Schema([
            'query' => $Query,
            'typeLoader' => fn (string $name): ?Type => $this->loadType($name, true),
        ]);

        $query = '{ object { object { object { string } } } }';
        $rootValue = ['object' => ['object' => ['object' => ['string' => 'test']]]];

        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            $rootValue
        );

        self::assertSame(
            ['data' => $rootValue],
            $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
        self::assertEquals(
            [
                'Query' => true,
                'SomeObject' => true,
                'OtherObject' => true,
            ],
            $this->loadedTypes
        );
        self::assertSame(
            [
                'Query.fields',
                'SomeObject',
                'SomeObject.fields',
            ],
            $this->calls
        );
    }

    public function testResolveUnion(): void
    {
        $Query = $this->loadType('Query');
        assert($Query instanceof ObjectType);

        $schema = new Schema([
            'query' => $Query,
            'typeLoader' => fn (string $name): ?Type => $this->loadType($name, true),
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

        self::assertSame($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
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
        self::assertSame($expectedCalls, $this->calls);
    }

    public function testSchemaWithConcreteTypeWithPhpFunctionName(): void
    {
        $interface = new InterfaceType([
            'name' => 'Foo',
            'resolveType' => static fn (): string => 'count',
            'fields' => static fn (): array => [
                'bar' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $namedLikePhpFunction = new ObjectType([
            'name' => 'count',
            'interfaces' => [$interface],
            'isTypeOf' => static fn ($obj): bool => $obj instanceof \stdClass,
            'fields' => static fn (): array => [
                'bar' => ['type' => Type::string()],
                'baz' => ['type' => Type::string()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => [
                        'type' => Type::listOf($interface),
                        'resolve' => static fn (): array => [
                            new \stdClass(),
                        ],
                    ],
                ],
            ]),
            'types' => [$namedLikePhpFunction],
            'typeLoader' => static function ($name) use ($interface, $namedLikePhpFunction): ?Type {
                switch ($name) {
                    case 'Foo': return $interface;
                    case 'count': return $namedLikePhpFunction;
                    default: return null;
                }
            },
        ]);

        $query = '{
          foo {
            bar
            ... on count {
              baz
            }
          }
        }';

        $result = Executor::execute($schema, Parser::parse($query));

        self::assertSame([], $result->errors);
    }
}
