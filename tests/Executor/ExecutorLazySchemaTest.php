<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use function count;
use GraphQL\Error\DebugFlag;
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
use PHPUnit\Framework\Error\Error;
use PHPUnit\Framework\TestCase;

class ExecutorLazySchemaTest extends TestCase
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
            'fields' => static function (): array {
                return [
                    'name' => ['type' => Type::string()],
                ];
            },
        ]);

        // Added to interface type when defined
        $dogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => static function ($obj): bool {
                return $obj instanceof Dog;
            },
            'fields' => static function (): array {
                return [
                    'name' => ['type' => Type::string()],
                    'woofs' => ['type' => Type::boolean()],
                ];
            },
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf' => static function ($obj): bool {
                return $obj instanceof Cat;
            },
            'fields' => static function (): array {
                return [
                    'name' => ['type' => Type::string()],
                    'meows' => ['type' => Type::boolean()],
                ];
            },
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($petType),
                        'resolve' => static function (): array {
                            return [new Dog('Odie', true), new Cat('Garfield', false)];
                        },
                    ],
                ],
            ]),
            'types' => [$catType, $dogType],
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
            'GraphQL Interface Type `Pet` returned `null` from its `resolveType` function for value: instance of '
            . 'GraphQL\Tests\Executor\TestClasses\Dog. Switching to slow resolution method using `isTypeOf` of all possible '
            . 'implementations. It requires full schema scan and degrades query performance significantly.  '
            . 'Make sure your `resolveType` always returns valid implementation or throws.',
            $result->errors[0]->getMessage()
        );
    }

    public function testSimpleQuery(): void
    {
        $query = $this->loadType('Query');
        self::assertInstanceOf(ObjectType::class, $query);

        $schema = new Schema([
            'query' => $query,
            'typeLoader' => fn (string $name) => $this->loadType($name, true),
        ]);

        $query = '
        {
            object {
                string
            }
        }
        ';
        $data = [
            'object' => [
                'string' => 'test',
            ],
        ];
        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            $data
        );

        self::assertEquals(
            [
                'data' => $data,
            ],
            $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
        self::assertEquals(
            [
                'Query',
                'Query.fields',
                'SomeObject',
                'SomeObject.fields',
            ],
            $this->calls
        );
    }

    /**
     * @return (Type&NamedType)|null
     */
    public function loadType(string $name): ?Type
    {
        $this->calls[] = $name;

        $this->loadedTypes[$name] = true;

        switch ($name) {
            case 'Query':
                return $this->queryType ??= new ObjectType([
                    'name' => 'Query',
                    'fields' => function (): array {
                        $this->calls[] = 'Query.fields';

                        return [
                            'object' => fn () => $this->loadType('SomeObject'),
                            'other' => fn () => $this->loadType('OtherObject'),
                        ];
                    },
                ]);

            case 'SomeObject':
                return $this->someObjectType ??= new ObjectType([
                    'name' => 'SomeObject',
                    'fields' => function (): array {
                        $this->calls[] = 'SomeObject.fields';

                        return [
                            'string' => fn () => Type::string(),
                            'object' => fn () => $this->someObjectType,
                        ];
                    },
                    'interfaces' => function (): array {
                        $this->calls[] = 'SomeObject.interfaces';

                        /** @var InterfaceType $someInterface */
                        $someInterface = $this->loadType('SomeInterface');

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

                        return [
                            'union' => fn () => $this->loadType('SomeUnion'),
                            'iface' => fn () => Type::nonNull($this->loadType('SomeInterface')),
                        ];
                    },
                ]);

            case 'DeeperObject':
                return $this->deeperObjectType ??= new ObjectType([
                    'name' => 'DeeperObject',
                    'fields' => function (): array {
                        $this->calls[] = 'DeeperObject.fields';

                        return [
                            'scalar' => fn () => $this->loadType('SomeScalar'),
                        ];
                    },
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
                    'resolveType' => function (): ObjectType {
                        $this->calls[] = 'SomeUnion.resolveType';

                        /** @var ObjectType $deeperObject */
                        $deeperObject = $this->loadType('DeeperObject');

                        return $deeperObject;
                    },
                    'types' => function (): array {
                        $this->calls[] = 'SomeUnion.types';

                        /** @var ObjectType $deeperObject */
                        $deeperObject = $this->loadType('DeeperObject');

                        return [$deeperObject];
                    },
                ]);

            case 'SomeInterface':
                return $this->someInterfaceType ??= new InterfaceType([
                    'name' => 'SomeInterface',
                    'resolveType' => function (): ObjectType {
                        $this->calls[] = 'SomeInterface.resolveType';

                        /** @var ObjectType $someObject */
                        $someObject = $this->loadType('SomeObject');

                        return $someObject;
                    },
                    'fields' => function (): array {
                        $this->calls[] = 'SomeInterface.fields';

                        return [
                            'string' => static fn () => Type::string(),
                        ];
                    },
                ]);

            default:
                return null;
        }
    }

    public function testDeepQuery(): void
    {
        $query = $this->loadType('Query');
        self::assertInstanceOf(ObjectType::class, $query);

        $schema = new Schema([
            'query' => $query,
            'typeLoader' => fn (string $name) => $this->loadType($name),
        ]);

        $query = '{ object { object { object { string } } } }';
        $data = ['object' => ['object' => ['object' => ['string' => 'test']]]];

        $result = Executor::execute(
            $schema,
            Parser::parse($query),
            $data
        );

        self::assertEquals(
            ['data' => $data],
            $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
        self::assertEquals(
            [
                'Query' => true,
                'SomeObject' => true,
            ],
            $this->loadedTypes
        );
        self::assertEquals(
            [
                'Query',
                'Query.fields',
                'SomeObject',
                'SomeObject.fields',
            ],
            $this->calls
        );
    }

    public function testResolveUnion(): void
    {
        $query = $this->loadType('Query');
        self::assertInstanceOf(ObjectType::class, $query);

        $schema = new Schema([
            'query' => $query,
            'typeLoader' => fn (string $name) => $this->loadType($name, true),
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
            'OtherObject' => true,
            'SomeUnion' => true,
            'DeeperObject' => true,
            'SomeScalar' => true,
        ];

        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
        self::assertEquals($expectedLoadedTypes, $this->loadedTypes);

        $expectedCalls = [
            'Query',
            'Query.fields',
            'OtherObject',
            'OtherObject.fields',
            'SomeUnion',
            'SomeUnion.resolveType',
            'DeeperObject',
            'SomeUnion.types',
            'DeeperObject',
            'DeeperObject.fields',
            'SomeScalar',
        ];
        self::assertEquals($expectedCalls, $this->calls);
    }
}
