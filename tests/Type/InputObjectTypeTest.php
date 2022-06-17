<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Examples\Blog\Types;
use GraphQL\GraphQL;
use GraphQL\Language\AST\TypeDefinitionNode;
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

    public function testParseWithResolveFnOnObject(): void
    {

        $schema = <<<SCHEMA
schema {
  mutation: Mutation
}

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

        $extendedSchema = <<<SCHEMA
extend input StoryFiltersInput {
  valueFromExtended: String
}
SCHEMA;

        $typeConfigDecorator = function (array $typeConfig, TypeDefinitionNode $node) {
            switch ($typeConfig['name']) {
                case 'Tag':
                    $typeConfig['parseValue'] = static function (array $values) {
                        return new Tag(
                            $values['name'],
                            $values['value'],
                        );
                    };
                    break;
                case 'StoryFiltersInput':
                    $typeConfig['parseValue'] = static function (array $values) {
                        return new StoryFiltersInput(
                            $values['author'],
                            $values['popular'],
                            $values['tag'],
                        );
                    };
                    break;
                case 'Mutation':
                    $typeConfig['resolveField'] = static function ($parent, $args) {
                        return $args['input'] instanceof StoryFiltersInput;
                    };
                    break;
            }

            return $typeConfig;
        };
        $schema = BuildSchema::build(Parser::parse($schema), $typeConfigDecorator);

        $schema = SchemaExtender::extend(
            $schema,
            Parser::parse($extendedSchema),
            [],
            $typeConfigDecorator
        );

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
}
