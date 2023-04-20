<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

final class LazyTypeLoaderTest extends TypeLoaderTestCaseBase
{
    /** @var callable(): InterfaceType */
    private $node;

    /** @var callable(): InterfaceType */
    private $content;

    /** @var callable(): ObjectType */
    private $blogStory;

    /** @var callable(): ObjectType */
    private $postStoryMutation;

    /** @var callable(): InputObjectType */
    private $postStoryMutationInput;

    /** @var array<string, Type> */
    private array $loadedTypes = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->node = $this->lazyLoad('Node');
        $this->blogStory = $this->lazyLoad('BlogStory');
        $this->content = $this->lazyLoad('Content');
        $this->postStoryMutation = $this->lazyLoad('PostStoryMutation');
        $this->postStoryMutationInput = $this->lazyLoad('PostStoryMutationInput');
        $this->query = new ObjectType([
            'name' => 'Query',
            'fields' => function (): array {
                $this->calls[] = 'Query.fields';

                return [
                    'latestContent' => $this->lazyLoad('Content'),
                    'node' => $this->lazyLoad('Node'),
                ];
            },
        ]);

        $this->mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => function (): array {
                $this->calls[] = 'Mutation.fields';

                return [
                    'postStory' => [
                        'type' => $this->postStoryMutation,
                        'args' => [
                            'input' => Type::nonNull($this->postStoryMutationInput),
                            'clientRequestId' => Type::string(),
                        ],
                    ],
                ];
            },
        ]);

        $this->typeLoader = function (string $name): ?Type {
            $this->calls[] = $name;

            switch ($name) {
                case 'Node':
                    return ($this->node)();

                case 'BlogStory':
                    return ($this->blogStory)();

                case 'Content':
                    return ($this->content)();

                case 'PostStoryMutation':
                    return ($this->postStoryMutation)();

                case 'PostStoryMutationInput':
                    return ($this->postStoryMutationInput)();
            }

            return null;
        };
    }

    private function lazyLoad(string $name): callable
    {
        return function () use ($name): Type {
            if (! isset($this->loadedTypes[$name])) {
                switch ($name) {
                    case 'Node':
                        return $this->loadedTypes[$name] = new InterfaceType([
                            'name' => 'Node',
                            'fields' => function (): array {
                                $this->calls[] = 'Node.fields';

                                return [
                                    'id' => Type::string(),
                                ];
                            },
                            'resolveType' => static fn (): ?ObjectType => null,
                        ]);

                    case 'Content':
                        return $this->loadedTypes[$name] = new InterfaceType([
                            'name' => 'Content',
                            'fields' => function (): array {
                                $this->calls[] = 'Content.fields';

                                return [
                                    'title' => Type::string(),
                                    'body' => Type::string(),
                                ];
                            },
                            'resolveType' => static fn (): ?ObjectType => null,
                        ]);

                    case 'BlogStory':
                        return $this->loadedTypes[$name] = new ObjectType([
                            'name' => 'BlogStory',
                            'interfaces' => [
                                $this->node,
                                $this->content,
                            ],
                            'fields' => function (): array {
                                $this->calls[] = 'BlogStory.fields';

                                return [
                                    'id' => Type::string(),
                                    'title' => Type::string(),
                                    'body' => Type::string(),
                                ];
                            },
                        ]);

                    case 'PostStoryMutation':
                        return $this->loadedTypes[$name] = new ObjectType([
                            'name' => 'PostStoryMutation',
                            'fields' => [
                                'story' => $this->blogStory,
                            ],
                        ]);

                    case 'PostStoryMutationInput':
                        return $this->loadedTypes[$name] = new InputObjectType([
                            'name' => 'PostStoryMutationInput',
                            'fields' => [
                                'title' => Type::string(),
                                'body' => Type::string(),
                                'author' => Type::id(),
                                'category' => Type::id(),
                            ],
                        ]);
                }
            }

            return $this->loadedTypes[$name];
        };
    }

    public function testWorksWithoutTypeLoader(): void
    {
        $schema = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation,
            'types' => [Schema::resolveType($this->blogStory)],
        ]);
        $schema->assertValid();

        self::assertSame([
            'Node.fields',
            'Content.fields',
            'BlogStory.fields',
            'Query.fields',
            'Mutation.fields',
        ], $this->calls);

        self::assertSame($this->query, $schema->getType('Query'));
        self::assertSame($this->mutation, $schema->getType('Mutation'));
        self::assertSame(Schema::resolveType($this->node), $schema->getType('Node'));
        self::assertSame(Schema::resolveType($this->content), $schema->getType('Content'));
        self::assertSame(Schema::resolveType($this->blogStory), $schema->getType('BlogStory'));
        self::assertSame(Schema::resolveType($this->postStoryMutation), $schema->getType('PostStoryMutation'));
        self::assertSame(Schema::resolveType($this->postStoryMutationInput), $schema->getType('PostStoryMutationInput'));

        self::assertArraySubset([
            'Query' => $this->query,
            'Mutation' => $this->mutation,
            'Node' => Schema::resolveType($this->node),
            'String' => Type::string(),
            'Content' => Schema::resolveType($this->content),
            'BlogStory' => Schema::resolveType($this->blogStory),
            'PostStoryMutationInput' => Schema::resolveType($this->postStoryMutationInput),
        ], $schema->getTypeMap());
    }

    public function testWorksWithTypeLoader(): void
    {
        $schema = new Schema([
            'query' => $this->query,
            'mutation' => $this->mutation,
            'typeLoader' => $this->typeLoader,
        ]);
        self::assertSame([], $this->calls);

        $node = $schema->getType('Node');
        self::assertInstanceOf(InterfaceType::class, $node);
        $resolvedNode = Schema::resolveType($this->node);
        self::assertSame($resolvedNode, $node);
        self::assertSame(['Node'], $this->calls);

        $content = $schema->getType('Content');
        self::assertSame(Schema::resolveType($this->content), $content);
        self::assertSame(['Node', 'Content'], $this->calls);

        $input = $schema->getType('PostStoryMutationInput');
        self::assertSame(Schema::resolveType($this->postStoryMutationInput), $input);
        self::assertSame(['Node', 'Content', 'PostStoryMutationInput'], $this->calls);

        $resolvedBlogStory = Schema::resolveType($this->blogStory);

        self::assertTrue($schema->isSubType($resolvedNode, $resolvedBlogStory));
        self::assertSame(
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
            'query' => $this->query,
            'typeLoader' => fn (): Type => Schema::resolveType($this->content),
        ]);

        $expectedType = 'Node';
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(Schema::typeLoaderWrongTypeName($expectedType, 'Content'));

        $schema->getType($expectedType);
    }
}
