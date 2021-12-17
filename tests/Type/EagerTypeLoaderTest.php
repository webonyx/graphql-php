<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * @see LazyTypeLoaderTest
 */
final class EagerTypeLoaderTest extends TypeLoaderTest
{
    private InterfaceType $node;

    private InterfaceType $content;

    private ObjectType $blogStory;

    private ObjectType $postStoryMutation;

    private InputObjectType $postStoryMutationInput;

    public function setUp(): void
    {
        parent::setUp();

        $this->node = new InterfaceType([
            'name'        => 'Node',
            'fields'      => function (): array {
                $this->calls[] = 'Node.fields';

                return [
                    'id' => Type::string(),
                ];
            },
            'resolveType' => static fn (): ?ObjectType => null,
        ]);

        $this->content = new InterfaceType([
            'name'        => 'Content',
            'fields'      => function (): array {
                $this->calls[] = 'Content.fields';

                return [
                    'title' => Type::string(),
                    'body'  => Type::string(),
                ];
            },
            'resolveType' => static fn (): ?ObjectType => null,
        ]);

        $this->blogStory = new ObjectType([
            'name'       => 'BlogStory',
            'interfaces' => [
                $this->node,
                $this->content,
            ],
            'fields'     => function (): array {
                $this->calls[] = 'BlogStory.fields';

                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                ];
            },
        ]);

        $this->query = new ObjectType([
            'name'   => 'Query',
            'fields' => function (): array {
                $this->calls[] = 'Query.fields';

                return [
                    'latestContent' => $this->content,
                    'node'          => $this->node,
                ];
            },
        ]);

        $this->mutation = new ObjectType([
            'name'   => 'Mutation',
            'fields' => function (): array {
                $this->calls[] = 'Mutation.fields';

                return [
                    'postStory' => [
                        'type' => $this->postStoryMutation,
                        'args' => [
                            'input'           => Type::nonNull($this->postStoryMutationInput),
                            'clientRequestId' => Type::string(),
                        ],
                    ],
                ];
            },
        ]);

        $this->postStoryMutation = new ObjectType([
            'name'   => 'PostStoryMutation',
            'fields' => [
                'story' => $this->blogStory,
            ],
        ]);

        $this->postStoryMutationInput = new InputObjectType([
            'name'   => 'PostStoryMutationInput',
            'fields' => [
                'title'    => Type::string(),
                'body'     => Type::string(),
                'author'   => Type::id(),
                'category' => Type::id(),
            ],
        ]);

        $this->typeLoader = function (string $name): ?Type {
            $this->calls[] = $name;

            switch ($name) {
                case 'Query':
                    return $this->query;

                case 'Mutation':
                    return $this->mutation;

                case 'Node':
                    return $this->node;

                case 'Content':
                    return $this->content;

                case 'BlogStory':
                    return $this->blogStory;

                case 'PostStoryMutation':
                    return $this->postStoryMutation;

                case 'PostStoryMutationInput':
                    return $this->postStoryMutationInput;

                default:
                    return null;
            }
        };
    }

    public function testWorksWithoutTypeLoader(): void
    {
        $schema = new Schema([
            'query'    => $this->query,
            'mutation' => $this->mutation,
            'types'    => [$this->blogStory],
        ]);

        $expected = [
            'Query.fields',
            'Content.fields',
            'Node.fields',
            'Mutation.fields',
            'BlogStory.fields',
        ];
        self::assertEquals($expected, $this->calls);

        self::assertSame($this->query, $schema->getType('Query'));
        self::assertSame($this->mutation, $schema->getType('Mutation'));
        self::assertSame($this->node, $schema->getType('Node'));
        self::assertSame($this->content, $schema->getType('Content'));
        self::assertSame($this->blogStory, $schema->getType('BlogStory'));
        self::assertSame($this->postStoryMutation, $schema->getType('PostStoryMutation'));
        self::assertSame($this->postStoryMutationInput, $schema->getType('PostStoryMutationInput'));

        $expectedTypeMap = [
            'Query'                  => $this->query,
            'Mutation'               => $this->mutation,
            'Node'                   => $this->node,
            'String'                 => Type::string(),
            'Content'                => $this->content,
            'BlogStory'              => $this->blogStory,
            'PostStoryMutationInput' => $this->postStoryMutationInput,
        ];

        self::assertArraySubset($expectedTypeMap, $schema->getTypeMap());
    }

    public function testWorksWithTypeLoader(): void
    {
        $schema = new Schema([
            'query'      => $this->query,
            'mutation'   => $this->mutation,
            'typeLoader' => $this->typeLoader,
        ]);
        self::assertEquals([], $this->calls);

        $node = $schema->getType('Node');
        self::assertSame($this->node, $node);
        self::assertEquals(['Node'], $this->calls);

        $content = $schema->getType('Content');
        self::assertSame($this->content, $content);
        self::assertEquals(['Node', 'Content'], $this->calls);

        $input = $schema->getType('PostStoryMutationInput');
        self::assertSame($this->postStoryMutationInput, $input);
        self::assertEquals(['Node', 'Content', 'PostStoryMutationInput'], $this->calls);

        $result = $schema->isSubType($this->node, $this->blogStory);
        self::assertTrue($result);
        self::assertEquals(
            [
                'Node',
                'Content',
                'PostStoryMutationInput',
            ],
            $this->calls
        );
    }

    public function testFailsOnInvalidLoad(): void
    {
        $schema = new Schema([
            'query'      => $this->query,
            'typeLoader' => fn (): Type => $this->content,
        ]);

        $expectedType = 'Node';
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(Schema::typeLoaderWrongTypeName($expectedType, 'Content'));

        $schema->getType($expectedType);
    }
}
