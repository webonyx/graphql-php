<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use Closure;
use GraphQL\Error\DebugFlag;
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
use PHPUnit\Framework\Assert;
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

    public $valueFromExtended;

    public function __construct(string $author, bool $popular, Tag $tag, ?string $valueFromExtended)
    {
        $this->author = $author;
        $this->popular = $popular;
        $this->tag = $tag;
        $this->valueFromExtended = $valueFromExtended;
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

    private function prepareExtendedSchema(Closure $assertFn): Schema
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

        $typeConfigDecorator = static function (array $typeConfig, TypeDefinitionNode $node) use ($assertFn) {
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
                        return new StoryFiltersInputExtended(
                            $values['author'],
                            $values['popular'],
                            $values['tag'],
                            $values['valueFromExtended'] ?? null
                        );
                    };
                    break;
                case 'Mutation':
                    $typeConfig['resolveField'] = $assertFn;
                    break;
            }

            return $typeConfig;
        };
        $schema = BuildSchema::build(Parser::parse($schema), $typeConfigDecorator);

        return SchemaExtender::extend(
            $schema,
            Parser::parse($extendedSchema),
            [],
            $typeConfigDecorator
        );
    }

    public function testParseWithExtendedSchema(): void
    {
        $schema = $this->prepareExtendedSchema(static function ($parent, $args) {
            return $args['input'] instanceof StoryFiltersInputExtended;
        });
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
                    'valueFromExtended' => null,
                    'tag' => [
                        'name' => 'foo',
                        'value' => 'bar',
                    ],
                ],
            ]
        );

        self::assertEquals(
            ['data' => ['action' => true]],
            $result->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS)
        );
    }

    public function testParseWithExtendedSchemaAndLiterals(): void
    {
        $schema = $this->prepareExtendedSchema(static function ($parent, $args) {
            return $args['input'] instanceof StoryFiltersInputExtended;
        });

        $query = <<<QUERY
mutation DoAction {
    action(input: {
        author: "John"
        popular: true,
        valueFromExtended: null
        tag: {
            name: "foo"
            value: "bar"
        }
    })
}
QUERY;

        $result = GraphQL::executeQuery($schema, $query);

        self::assertEquals(
            ['data' => ['action' => true]],
            $result->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS)
        );
    }

    public function testParseWithExtendedSchemaAndVariablesAndLiterals(): void
    {
        $schema = $this->prepareExtendedSchema(static function ($parent, $args) {
            Assert::assertInstanceOf(StoryFiltersInputExtended::class, $args['input']);
            Assert::assertEquals('John', $args['input']->author);
            Assert::assertTrue($args['input']->popular);
            Assert::assertEquals('value', $args['input']->valueFromExtended);

            Assert::assertInstanceOf(Tag::class, $args['input']->tag);
            Assert::assertEquals('foo', $args['input']->tag->name);
            Assert::assertEquals('bar', $args['input']->tag->value);

            return true;
        });

        $query = <<<QUERY
mutation DoAction(\$authorName: ID!, \$tagName: String!) {
    action(input: {
        author: \$authorName
        popular: true,
        valueFromExtended: "value"
        tag: {
            name: \$tagName
            value: "bar"
        }
    })
}
QUERY;

        $result = GraphQL::executeQuery($schema, $query, null, null, ['authorName' => 'John', 'tagName' => 'foo']);

        self::assertEquals(
            ['data' => ['action' => true]],
            $result->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS)
        );
    }
}
