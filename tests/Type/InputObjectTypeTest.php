<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaExtender;
use PHPUnit\Framework\TestCase;

final class Tag
{
    public string $name;

    public string $value;

    public function __construct(string $name, string $value)
    {
        $this->name = $name;
        $this->value = $value;
    }
}

final class StoryFiltersInput
{
    public string $author;

    public bool $popular;

    public Tag $tag;

    public function __construct(string $author, bool $popular, Tag $tag)
    {
        $this->author = $author;
        $this->popular = $popular;
        $this->tag = $tag;
    }
}

final class StoryFiltersInputExtended
{
    public string $author;

    public bool $popular;

    public Tag $tag;

    public ?string $valueFromExtended;

    public function __construct(string $author, bool $popular, Tag $tag, ?string $valueFromExtended)
    {
        $this->author = $author;
        $this->popular = $popular;
        $this->tag = $tag;
        $this->valueFromExtended = $valueFromExtended;
    }
}

/**
 * @phpstan-import-type FieldResolver from Executor
 *
 * @phpstan-type ParseValueErrorResult array{
 *     data?: array{action: string|null, sibling: string},
 *     errors?: list<array{
 *         message: string,
 *         locations: list<array{line: int, column: int}>,
 *         path?: list<string>
 *     }>
 * }
 */
final class InputObjectTypeTest extends TestCase
{
    /**
     * @param list<string> $expectedResolvedFields
     *
     * @phpstan-param array{input?: array{value: string}} $variables
     * @phpstan-param ParseValueErrorResult $expectedResult
     *
     * @dataProvider provideParseValueErrorTransport
     */
    public function testParseValueErrorTransport(
        string $query,
        array $variables,
        array $expectedResult,
        array $expectedResolvedFields
    ): void {
        $input = new InputObjectType([
            'name' => 'TestInput',
            'fields' => ['value' => Type::string()],
            'parseValue' => static function (array $value): array {
                if ($value['value'] === 'reject') {
                    throw new Error('Input rejected.');
                }

                return $value;
            },
        ]);
        $resolvedFields = [];
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'action' => [
                        'type' => Type::string(),
                        'args' => ['input' => $input],
                        'resolve' => static function () use (&$resolvedFields): string {
                            $resolvedFields[] = 'action';

                            return 'accepted';
                        },
                    ],
                    'sibling' => [
                        'type' => Type::string(),
                        'resolve' => static function () use (&$resolvedFields): string {
                            $resolvedFields[] = 'sibling';

                            return 'ok';
                        },
                    ],
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, $query, null, null, $variables);

        self::assertSame($expectedResult, $result->toArray());
        self::assertSame($expectedResolvedFields, $resolvedFields);
    }

    /**
     * @phpstan-return \Generator<string, array{
     *     string,
     *     array{input?: array{value: string}},
     *     ParseValueErrorResult,
     *     list<string>
     * }>
     */
    public static function provideParseValueErrorTransport(): \Generator
    {
        $variableQuery = <<<'GRAPHQL'
            query(
              $input: TestInput
            ) {
              action(input: $input)
              sibling
            }
            GRAPHQL;
        $defaultQuery = <<<'GRAPHQL'
            query(
              $input: TestInput = {value: "reject"}
            ) {
              action(input: $input)
              sibling
            }
            GRAPHQL;

        yield 'supplied variable stops execution' => [
            $variableQuery,
            ['input' => ['value' => 'reject']],
            ['errors' => [[
                'message' => 'Variable "$input" got invalid value {"value":"reject"}; Input rejected.',
                'locations' => [['line' => 2, 'column' => 3]],
            ]]],
            [],
        ];
        yield 'operation default stops execution' => [
            $defaultQuery,
            [],
            ['errors' => [[
                'message' => 'Input rejected.',
                'locations' => [['line' => 2, 'column' => 3]],
            ]]],
            [],
        ];
        yield 'supplied variable overrides failing default' => [
            $defaultQuery,
            ['input' => ['value' => 'accepted']],
            ['data' => ['action' => 'accepted', 'sibling' => 'ok']],
            ['action', 'sibling'],
        ];
        yield 'inline literal allows sibling execution' => [
            <<<'GRAPHQL'
            {
              action(input: {value: "reject"})
              sibling
            }
            GRAPHQL,
            [],
            [
                'errors' => [[
                    'message' => 'Input rejected.',
                    'locations' => [['line' => 2, 'column' => 3]],
                    'path' => ['action'],
                ]],
                'data' => ['action' => null, 'sibling' => 'ok'],
            ],
            ['sibling'],
        ];
    }

    public function testParseValueFromVariables(): void
    {
        $tag = new InputObjectType([
            'name' => 'Tag',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                ],
                'value' => [
                    'type' => Type::string(),
                ],
            ],
            'parseValue' => static fn (array $values): Tag => new Tag(
                $values['name'],
                $values['value'],
            ),
        ]);

        $input = new InputObjectType([
            'name' => 'StoryFiltersInput',
            'fields' => [
                'author' => [
                    'type' => Type::id(),
                ],
                'popular' => [
                    'type' => Type::boolean(),
                ],
                'tag' => [
                    'type' => $tag,
                ],
            ],
            'parseValue' => static fn (array $values): StoryFiltersInput => new StoryFiltersInput(
                $values['author'],
                $values['popular'],
                $values['tag'],
            ),
        ]);

        $mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'action' => [
                    'type' => Type::boolean(),
                    'args' => [
                        'input' => [
                            'type' => $input,
                        ],
                    ],
                    'resolve' => static fn ($rootValue, array $args): bool => $args['input'] instanceof StoryFiltersInput,
                ],
            ],
        ]);

        $schema = new Schema([
            'mutation' => $mutation,
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            /** @lang text */
            '
                mutation ($input: StoryFiltersInput!) {
                    action(input: $input)
                }
            ',
            null,
            null,
            [
                'input' => [
                    'author' => 'John',
                    'popular' => true,
                    'tag' => [
                        'name' => 'foo',
                        'value' => 'bar',
                    ],
                ],
            ]
        );

        self::assertSame(
            ['data' => ['action' => true]],
            $result->toArray()
        );
    }

    public function testParseValueFromLiterals(): void
    {
        $tag = new InputObjectType([
            'name' => 'Tag',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                ],
                'value' => [
                    'type' => Type::string(),
                ],
            ],
            'parseValue' => static fn (array $values): Tag => new Tag(
                $values['name'],
                $values['value'],
            ),
        ]);

        $input = new InputObjectType([
            'name' => 'StoryFiltersInput',
            'fields' => [
                'author' => [
                    'type' => Type::id(),
                ],
                'popular' => [
                    'type' => Type::boolean(),
                ],
                'tag' => [
                    'type' => $tag,
                ],
            ],
            'parseValue' => static fn (array $values): StoryFiltersInput => new StoryFiltersInput(
                $values['author'],
                $values['popular'],
                $values['tag'],
            ),
        ]);

        $mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'action' => [
                    'type' => Type::boolean(),
                    'args' => [
                        'input' => [
                            'type' => $input,
                        ],
                    ],
                    'resolve' => static fn ($rootValue, array $args): bool => $args['input'] instanceof StoryFiltersInput,
                ],
            ],
        ]);

        $schema = new Schema([
            'mutation' => $mutation,
        ]);

        $result = GraphQL::executeQuery($schema, /** @lang GraphQL */ '
            mutation {
                action(input: {
                    author: "John"
                    popular: true,
                    tag: {
                        name: "foo"
                        value: "bar"
                    }
                })
            }
        ');

        self::assertSame(
            ['data' => ['action' => true]],
            $result->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS)
        );
    }

    public function testParseValueFromVariablesAndLiterals(): void
    {
        $tag = new InputObjectType([
            'name' => 'Tag',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                ],
                'value' => [
                    'type' => Type::string(),
                ],
            ],
            'parseValue' => static fn (array $values): Tag => new Tag(
                $values['name'],
                $values['value'],
            ),
        ]);

        $input = new InputObjectType([
            'name' => 'StoryFiltersInput',
            'fields' => [
                'author' => [
                    'type' => Type::id(),
                ],
                'popular' => [
                    'type' => Type::boolean(),
                ],
                'tag' => [
                    'type' => $tag,
                ],
            ],
            'parseValue' => static fn (array $values): StoryFiltersInput => new StoryFiltersInput(
                $values['author'],
                $values['popular'],
                $values['tag'],
            ),
        ]);

        $mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'action' => [
                    'type' => Type::boolean(),
                    'args' => [
                        'input' => [
                            'type' => $input,
                        ],
                    ],
                    'resolve' => static function ($parent, array $args): bool {
                        $input = $args['input'];

                        self::assertInstanceOf(StoryFiltersInput::class, $input);
                        self::assertSame('John', $input->author);
                        self::assertTrue($input->popular);

                        $tag = $input->tag;
                        self::assertSame('foo', $tag->name);
                        self::assertSame('bar', $tag->value);

                        return true;
                    },
                ],
            ],
        ]);

        $schema = new Schema([
            'mutation' => $mutation,
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            /** @lang GraphQL */
            '
                mutation ($authorName: ID!, $tagName: String!) {
                    action(input: {
                        author: $authorName
                        popular: true,
                        tag: {
                            name: $tagName
                            value: "bar"
                        }
                    })
                }
            ',
            null,
            null,
            [
                'authorName' => 'John',
                'tagName' => 'foo',
            ]
        );

        self::assertSame(
            ['data' => ['action' => true]],
            $result->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS)
        );
    }

    public function testParseValueNotCalledWhenNull(): void
    {
        $input = new InputObjectType([
            'name' => 'StoryFiltersInput',
            'fields' => [
                'author' => [
                    'type' => Type::id(),
                ],
            ],
            'parseValue' => function (): void {
                throw new \Exception('Should not be called');
            },
        ]);

        $mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'action' => [
                    'type' => Type::boolean(),
                    'args' => [
                        'input' => [
                            'type' => $input,
                        ],
                    ],
                    'resolve' => static fn ($rootValue, array $args): bool => $args['input'] === null,
                ],
            ],
        ]);

        $schema = new Schema([
            'mutation' => $mutation,
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            /** @lang GraphQL */
            '
                mutation ($input: StoryFiltersInput) {
                    action(input: $input)
                }
            ',
            null,
            null,
            [
                'input' => null,
            ]
        );

        self::assertSame(
            ['data' => ['action' => true]],
            $result->toArray()
        );
    }

    public function testParseValueWithExtendedSchema(): void
    {
        $sdl = /** @lang GraphQL */ <<<SCHEMA
            type Mutation {
              action(input: StoryFiltersInput!): Boolean!
            }
            
            input Tag {
              name: String
              value: String
            }
            
            input StoryFiltersInput {
              author: ID
              popular: Boolean
              tag: Tag
            }
            SCHEMA;

        $extendSdl = /** @lang GraphQL */ <<<SCHEMA
            extend input StoryFiltersInput {
              valueFromExtended: String
            }
            SCHEMA;

        $typeConfigDecorator = static function (array $typeConfig): array {
            switch ($typeConfig['name']) {
                case 'Tag':
                    $typeConfig['parseValue'] = static fn (array $values): Tag => new Tag(
                        $values['name'],
                        $values['value'],
                    );
                    break;
                case 'StoryFiltersInput':
                    $typeConfig['parseValue'] = static fn (array $values): StoryFiltersInputExtended => new StoryFiltersInputExtended(
                        $values['author'],
                        $values['popular'],
                        $values['tag'],
                        $values['valueFromExtended'] ?? null
                    );
                    break;
                case 'Mutation':
                    $typeConfig['resolveField'] = static fn ($parent, array $args): bool => $args['input'] instanceof StoryFiltersInputExtended;
                    break;
            }

            return $typeConfig;
        };
        $baseSchema = BuildSchema::build(Parser::parse($sdl), $typeConfigDecorator);

        $schema = SchemaExtender::extend(
            $baseSchema,
            Parser::parse($extendSdl),
            [],
            $typeConfigDecorator
        );

        $result = GraphQL::executeQuery(
            $schema,
            /** @lang GraphQL */
            '
                mutation ($input: StoryFiltersInput!) {
                    action(input: $input)
                }
            ',
            null,
            null,
            [
                'input' => [
                    'author' => 'John',
                    'popular' => true,
                    'valueFromExtended' => null,
                    'tag' => [
                        'name' => 'foo',
                        'value' => 'bar',
                    ],
                ],
            ]
        );

        self::assertSame(
            ['data' => ['action' => true]],
            $result->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS)
        );
    }
}
