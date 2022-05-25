<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Examples\Blog\Types;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
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

final class InputObjectTypeTest extends TestCase
{
    public function testParseValue(): void
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
            'parseValue' => function (array $values) {
                return new Tag(
                    $values['name'],
                    $values['value'],
                );
            },
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
            'parseValue' => function (array $values) {
                return new StoryFiltersInput(
                    $values['author'],
                    $values['popular'],
                    $values['tag'],
                );
            },
        ]);

        $mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'action' => [
                    'type' => Types::boolean(),
                    'args' => [
                        'input' => [
                            'type' => $input,
                        ],
                    ],
                    'resolve' => function ($rootValue, array $args) {
                        return $args['input'] instanceof StoryFiltersInput;
                    },
                ],
            ],
        ]);

        $schema = new Schema(['mutation' => $mutation]);

        $query = 'mutation DoAction($input: StoryFiltersInput!) { action(input: $input) }';

        $result = GraphQL::executeQuery(
            $schema,
            $query,
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

        self::assertEquals(
            ['data' => ['action' => true]],
            $result->toArray()
        );
        GraphQL::executeQuery($schema, $query);
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
            'parseValue' => function (array $values) {
                throw new \Exception('Should not be called');
            },
        ]);

        $mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'action' => [
                    'type' => Types::boolean(),
                    'args' => [
                        'input' => [
                            'type' => $input,
                        ],
                    ],
                    'resolve' => function ($rootValue, array $args) {
                        return $args['input'] === null;
                    },
                ],
            ],
        ]);

        $schema = new Schema(['mutation' => $mutation]);

        $query = 'mutation DoAction($input: StoryFiltersInput) { action(input: $input) }';

        $result = GraphQL::executeQuery(
            $schema,
            $query,
            null,
            null,
            [
                'input' => null,
            ]
        );

        self::assertEquals(
            ['data' => ['action' => true]],
            $result->toArray()
        );
        GraphQL::executeQuery($schema, $query);
    }
}
