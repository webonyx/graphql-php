<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Deferred;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class DeferredFieldsTest extends TestCase
{
    private ObjectType $userType;

    private ObjectType $storyType;

    private ObjectType $categoryType;

    /** @var array<int, mixed> */
    private array $paths = [];

    /**
     * @var array<
     *     int,
     *     array{
     *         id: int,
     *         authorId: int,
     *         title: string,
     *         categoryIds: array<int, int>
     *    }
     * >
     */
    private array $storyDataSource;

    /**
     * @var array<
     *     int,
     *     array{
     *         id: int,
     *         name: string,
     *         bestFriendId: int,
     *    }
     * >
     */
    private array $userDataSource;

    /**
     * @var array<
     *     int,
     *     array{
     *         id: int,
     *         name: string,
     *         topStoryId: int,
     *    }
     * >
     */
    private array $categoryDataSource;

    private ObjectType $queryType;

    protected function setUp(): void
    {
        $this->storyDataSource = [
            ['id' => 1, 'authorId' => 1, 'title' => 'Story #1', 'categoryIds' => [2, 3]],
            ['id' => 2, 'authorId' => 2, 'title' => 'Story #2', 'categoryIds' => [1, 2]],
            ['id' => 3, 'authorId' => 3, 'title' => 'Story #3', 'categoryIds' => [2]],
            ['id' => 4, 'authorId' => 3, 'title' => 'Story #4', 'categoryIds' => [1]],
            ['id' => 5, 'authorId' => 1, 'title' => 'Story #5', 'categoryIds' => [3]],
            ['id' => 6, 'authorId' => 2, 'title' => 'Story #6', 'categoryIds' => [1]],
            ['id' => 7, 'authorId' => 3, 'title' => 'Story #7', 'categoryIds' => [2]],
            ['id' => 8, 'authorId' => 1, 'title' => 'Story #8', 'categoryIds' => [1, 2, 3]],
            ['id' => 9, 'authorId' => 2, 'title' => 'Story #9', 'categoryIds' => [2, 3]],
        ];

        $this->userDataSource = [
            ['id' => 1, 'name' => 'John', 'bestFriendId' => 4],
            ['id' => 2, 'name' => 'Jane', 'bestFriendId' => 3],
            ['id' => 3, 'name' => 'Joe', 'bestFriendId' => 2],
            ['id' => 4, 'name' => 'Dirk', 'bestFriendId' => 1],
        ];

        $this->categoryDataSource = [
            ['id' => 1, 'name' => 'Category #1', 'topStoryId' => 8],
            ['id' => 2, 'name' => 'Category #2', 'topStoryId' => 3],
            ['id' => 3, 'name' => 'Category #3', 'topStoryId' => 9],
        ];

        $this->userType = new ObjectType([
            'name' => 'User',
            'fields' => fn (): array => [
                'name' => [
                    'type' => Type::string(),
                    'resolve' => function ($user, $args, $context, ResolveInfo $info) {
                        $this->paths[] = $info->path;

                        return $user['name'];
                    },
                ],
                'bestFriend' => [
                    'type' => $this->userType,
                    'resolve' => function ($user, $args, $context, ResolveInfo $info): Deferred {
                        $this->paths[] = $info->path;

                        return new Deferred(function () use ($user) {
                            $this->paths[] = 'deferred-for-best-friend-of-' . $user['id'];

                            return $this->findUserById($user['bestFriendId']);
                        });
                    },
                ],
            ],
        ]);

        $this->storyType = new ObjectType([
            'name' => 'Story',
            'fields' => [
                'title' => [
                    'type' => Type::string(),
                    'resolve' => function ($story, $args, $context, ResolveInfo $info) {
                        $this->paths[] = $info->path;

                        return $story['title'];
                    },
                ],
                'author' => [
                    'type' => $this->userType,
                    'resolve' => function ($story, $args, $context, ResolveInfo $info): Deferred {
                        $this->paths[] = $info->path;

                        return new Deferred(function () use ($story) {
                            $this->paths[] = 'deferred-for-story-' . $story['id'] . '-author';

                            return $this->findUserById($story['authorId']);
                        });
                    },
                ],
            ],
        ]);

        $this->categoryType = new ObjectType([
            'name' => 'Category',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'resolve' => function ($category, array $args, $context, ResolveInfo $info) {
                        $this->paths[] = $info->path;

                        return $category['name'];
                    },
                ],

                'stories' => [
                    'type' => Type::listOf($this->storyType),
                    'resolve' => function ($category, array $args, $context, ResolveInfo $info): array {
                        $this->paths[] = $info->path;

                        return array_filter(
                            $this->storyDataSource,
                            static fn ($story): bool => in_array($category['id'], $story['categoryIds'], true)
                        );
                    },
                ],
                'topStory' => [
                    'type' => $this->storyType,
                    'resolve' => function ($category, array $args, $context, ResolveInfo $info): Deferred {
                        $this->paths[] = $info->path;

                        return new Deferred(function () use ($category): ?array {
                            $this->paths[] = 'deferred-for-category-' . $category['id'] . '-topStory';

                            return $this->findStoryById($category['topStoryId']);
                        });
                    },
                ],
                'topStoryAuthor' => [
                    'type' => $this->userType,
                    'resolve' => function ($category, array $args, $context, ResolveInfo $info): Deferred {
                        $this->paths[] = $info->path;

                        return new Deferred(function () use ($category): Deferred {
                            $this->paths[] = 'deferred-for-category-' . $category['id'] . '-topStoryAuthor1';

                            $story = $this->findStoryById($category['topStoryId']);

                            return new Deferred(function () use ($category, $story) {
                                $this->paths[] = 'deferred-for-category-' . $category['id'] . '-topStoryAuthor2';

                                if ($story === null) {
                                    return null;
                                }

                                return $this->findUserById($story['authorId']);
                            });
                        });
                    },
                ],
            ],
        ]);

        $this->queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'topStories' => [
                    'type' => Type::listOf($this->storyType),
                    'resolve' => function ($rootValue, array $args, $context, ResolveInfo $info): array {
                        $this->paths[] = $info->path;

                        return array_filter(
                            $this->storyDataSource,
                            static fn ($story): bool => $story['id'] % 2 === 1
                        );
                    },
                ],
                'featuredCategory' => [
                    'type' => $this->categoryType,
                    'resolve' => function ($rootValue, array $args, $context, ResolveInfo $info): array {
                        $this->paths[] = $info->path;

                        return $this->categoryDataSource[0];
                    },
                ],
                'categories' => [
                    'type' => Type::listOf($this->categoryType),
                    'resolve' => function ($rootValue, array $args, $context, ResolveInfo $info): array {
                        $this->paths[] = $info->path;

                        return $this->categoryDataSource;
                    },
                ],
            ],
        ]);

        parent::setUp();
    }

    public function testDeferredFields(): void
    {
        $query = Parser::parse('
            {
                topStories {
                    title
                    author {
                        name
                    }
                }
                featuredCategory {
                    stories {
                        title
                        author {
                            name
                        }
                    }
                }
            }
        ');

        $schema = new Schema([
            'query' => $this->queryType,
        ]);

        $expected = [
            'data' => [
                'topStories' => [
                    ['title' => 'Story #1', 'author' => ['name' => 'John']],
                    ['title' => 'Story #3', 'author' => ['name' => 'Joe']],
                    ['title' => 'Story #5', 'author' => ['name' => 'John']],
                    ['title' => 'Story #7', 'author' => ['name' => 'Joe']],
                    ['title' => 'Story #9', 'author' => ['name' => 'Jane']],
                ],
                'featuredCategory' => [
                    'stories' => [
                        ['title' => 'Story #2', 'author' => ['name' => 'Jane']],
                        ['title' => 'Story #4', 'author' => ['name' => 'Joe']],
                        ['title' => 'Story #6', 'author' => ['name' => 'Jane']],
                        ['title' => 'Story #8', 'author' => ['name' => 'John']],
                    ],
                ],
            ],
        ];

        $result = Executor::execute($schema, $query);
        self::assertSame($expected, $result->toArray());

        $this->assertPathsMatch([
            ['topStories'],
            ['topStories', 0, 'title'],
            ['topStories', 0, 'author'],
            ['topStories', 1, 'title'],
            ['topStories', 1, 'author'],
            ['topStories', 2, 'title'],
            ['topStories', 2, 'author'],
            ['topStories', 3, 'title'],
            ['topStories', 3, 'author'],
            ['topStories', 4, 'title'],
            ['topStories', 4, 'author'],
            ['featuredCategory'],
            ['featuredCategory', 'stories'],
            ['featuredCategory', 'stories', 0, 'title'],
            ['featuredCategory', 'stories', 0, 'author'],
            ['featuredCategory', 'stories', 1, 'title'],
            ['featuredCategory', 'stories', 1, 'author'],
            ['featuredCategory', 'stories', 2, 'title'],
            ['featuredCategory', 'stories', 2, 'author'],
            ['featuredCategory', 'stories', 3, 'title'],
            ['featuredCategory', 'stories', 3, 'author'],
            'deferred-for-story-1-author',
            'deferred-for-story-3-author',
            'deferred-for-story-5-author',
            'deferred-for-story-7-author',
            'deferred-for-story-9-author',
            'deferred-for-story-2-author',
            'deferred-for-story-4-author',
            'deferred-for-story-6-author',
            'deferred-for-story-8-author',
            ['topStories', 0, 'author', 'name'],
            ['topStories', 1, 'author', 'name'],
            ['topStories', 2, 'author', 'name'],
            ['topStories', 3, 'author', 'name'],
            ['topStories', 4, 'author', 'name'],
            ['featuredCategory', 'stories', 0, 'author', 'name'],
            ['featuredCategory', 'stories', 1, 'author', 'name'],
            ['featuredCategory', 'stories', 2, 'author', 'name'],
            ['featuredCategory', 'stories', 3, 'author', 'name'],
        ]);
    }

    public function testNestedDeferredFields(): void
    {
        $query = Parser::parse('
            {
                categories {
                    name
                    topStory {
                        title
                        author {
                            name
                            bestFriend {
                                name
                            }
                        }
                    }
                }
            }
        ');

        $schema = new Schema([
            'query' => $this->queryType,
        ]);

        $author1 = ['name' => 'John', 'bestFriend' => ['name' => 'Dirk']];
        $author2 = ['name' => 'Jane', 'bestFriend' => ['name' => 'Joe']];
        $author3 = ['name' => 'Joe', 'bestFriend' => ['name' => 'Jane']];

        $expected = [
            'data' => [
                'categories' => [
                    ['name' => 'Category #1', 'topStory' => ['title' => 'Story #8', 'author' => $author1]],
                    ['name' => 'Category #2', 'topStory' => ['title' => 'Story #3', 'author' => $author3]],
                    ['name' => 'Category #3', 'topStory' => ['title' => 'Story #9', 'author' => $author2]],
                ],
            ],
        ];

        $result = Executor::execute($schema, $query);
        self::assertSame($expected, $result->toArray());

        $this->assertPathsMatch([
            ['categories'],
            ['categories', 0, 'name'],
            ['categories', 0, 'topStory'],
            ['categories', 1, 'name'],
            ['categories', 1, 'topStory'],
            ['categories', 2, 'name'],
            ['categories', 2, 'topStory'],
            'deferred-for-category-1-topStory',
            'deferred-for-category-2-topStory',
            'deferred-for-category-3-topStory',
            ['categories', 0, 'topStory', 'title'],
            ['categories', 0, 'topStory', 'author'],
            ['categories', 1, 'topStory', 'title'],
            ['categories', 1, 'topStory', 'author'],
            ['categories', 2, 'topStory', 'title'],
            ['categories', 2, 'topStory', 'author'],
            'deferred-for-story-8-author',
            'deferred-for-story-3-author',
            'deferred-for-story-9-author',
            ['categories', 0, 'topStory', 'author', 'name'],
            ['categories', 0, 'topStory', 'author', 'bestFriend'],
            ['categories', 1, 'topStory', 'author', 'name'],
            ['categories', 1, 'topStory', 'author', 'bestFriend'],
            ['categories', 2, 'topStory', 'author', 'name'],
            ['categories', 2, 'topStory', 'author', 'bestFriend'],
            'deferred-for-best-friend-of-1',
            'deferred-for-best-friend-of-3',
            'deferred-for-best-friend-of-2',
            ['categories', 0, 'topStory', 'author', 'bestFriend', 'name'],
            ['categories', 1, 'topStory', 'author', 'bestFriend', 'name'],
            ['categories', 2, 'topStory', 'author', 'bestFriend', 'name'],
        ]);
    }

    public function testComplexRecursiveDeferredFields(): void
    {
        $complexType = new ObjectType([
            'name' => 'ComplexType',
            'fields' => function () use (&$complexType): array {
                return [
                    'sync' => [
                        'type' => Type::string(),
                        'resolve' => function ($complexType, $args, $context, ResolveInfo $info): string {
                            $this->paths[] = $info->path;

                            return 'sync';
                        },
                    ],
                    'deferred' => [
                        'type' => Type::string(),
                        'resolve' => function ($complexType, $args, $context, ResolveInfo $info): Deferred {
                            $this->paths[] = $info->path;

                            return new Deferred(function () use ($info): string {
                                $this->paths[] = ['!dfd for: ', $info->path];

                                return 'deferred';
                            });
                        },
                    ],
                    'nest' => [
                        'type' => $complexType,
                        'resolve' => function ($complexType, $args, $context, ResolveInfo $info): array {
                            $this->paths[] = $info->path;

                            return [];
                        },
                    ],
                    'deferredNest' => [
                        'type' => $complexType,
                        'resolve' => function ($complexType, $args, $context, ResolveInfo $info): Deferred {
                            $this->paths[] = $info->path;

                            return new Deferred(function () use ($info): array {
                                $this->paths[] = ['!dfd nest for: ', $info->path];

                                return [];
                            });
                        },
                    ],
                ];
            },
        ]);

        $schema = new Schema([
            'query' => $complexType,
        ]);

        $query = Parser::parse('
            {
                nest {
                    sync
                    deferred
                    nest {
                        sync
                        deferred
                    }
                    deferredNest {
                        sync
                        deferred
                    }
                }
                deferredNest {
                    sync
                    deferred
                    nest {
                        sync
                        deferred
                    }
                    deferredNest {
                        sync
                        deferred
                    }
                }
            }
        ');
        $result = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'nest' => [
                    'sync' => 'sync',
                    'deferred' => 'deferred',
                    'nest' => [
                        'sync' => 'sync',
                        'deferred' => 'deferred',
                    ],
                    'deferredNest' => [
                        'sync' => 'sync',
                        'deferred' => 'deferred',
                    ],
                ],
                'deferredNest' => [
                    'sync' => 'sync',
                    'deferred' => 'deferred',
                    'nest' => [
                        'sync' => 'sync',
                        'deferred' => 'deferred',
                    ],
                    'deferredNest' => [
                        'sync' => 'sync',
                        'deferred' => 'deferred',
                    ],
                ],
            ],
        ];

        self::assertSame($expected, $result->toArray());

        $this->assertPathsMatch([
            ['nest'],
            ['nest', 'sync'],
            ['nest', 'deferred'],
            ['nest', 'nest'],
            ['nest', 'nest', 'sync'],
            ['nest', 'nest', 'deferred'],
            ['nest', 'deferredNest'],
            ['deferredNest'],

            ['!dfd for: ', ['nest', 'deferred']],
            ['!dfd for: ', ['nest', 'nest', 'deferred']],
            ['!dfd nest for: ', ['nest', 'deferredNest']],
            ['!dfd nest for: ', ['deferredNest']],

            ['nest', 'deferredNest', 'sync'],
            ['nest', 'deferredNest', 'deferred'],
            ['deferredNest', 'sync'],
            ['deferredNest', 'deferred'],
            ['deferredNest', 'nest'],
            ['deferredNest', 'nest', 'sync'],
            ['deferredNest', 'nest', 'deferred'],
            ['deferredNest', 'deferredNest'],

            ['!dfd for: ', ['nest', 'deferredNest', 'deferred']],
            ['!dfd for: ', ['deferredNest', 'deferred']],
            ['!dfd for: ', ['deferredNest', 'nest', 'deferred']],
            ['!dfd nest for: ', ['deferredNest', 'deferredNest']],

            ['deferredNest', 'deferredNest', 'sync'],
            ['deferredNest', 'deferredNest', 'deferred'],
            ['!dfd for: ', ['deferredNest', 'deferredNest', 'deferred']],
        ]);
    }

    public function testDeferredChaining(): void
    {
        $schema = new Schema([
            'query' => $this->queryType,
        ]);

        $query = Parser::parse('
            {
                categories {
                    name
                    topStory {
                        title
                        author {
                            name
                        }
                    }
                    topStoryAuthor {
                        name
                    }
                }
            }
        ');

        $author1 = ['name' => 'John'/* , 'bestFriend' => ['name' => 'Dirk'] */];
        $author2 = ['name' => 'Jane'/* , 'bestFriend' => ['name' => 'Joe'] */];
        $author3 = ['name' => 'Joe'/* , 'bestFriend' => ['name' => 'Jane'] */];

        $story1 = ['title' => 'Story #8', 'author' => $author1];
        $story2 = ['title' => 'Story #3', 'author' => $author3];
        $story3 = ['title' => 'Story #9', 'author' => $author2];

        $result = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'categories' => [
                    ['name' => 'Category #1', 'topStory' => $story1, 'topStoryAuthor' => $author1],
                    ['name' => 'Category #2', 'topStory' => $story2, 'topStoryAuthor' => $author3],
                    ['name' => 'Category #3', 'topStory' => $story3, 'topStoryAuthor' => $author2],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());

        $this->assertPathsMatch([
            ['categories'],
            ['categories', 0, 'name'],
            ['categories', 0, 'topStory'],
            ['categories', 0, 'topStoryAuthor'],
            ['categories', 1, 'name'],
            ['categories', 1, 'topStory'],
            ['categories', 1, 'topStoryAuthor'],
            ['categories', 2, 'name'],
            ['categories', 2, 'topStory'],
            ['categories', 2, 'topStoryAuthor'],
            'deferred-for-category-1-topStory',
            'deferred-for-category-1-topStoryAuthor1',
            'deferred-for-category-2-topStory',
            'deferred-for-category-2-topStoryAuthor1',
            'deferred-for-category-3-topStory',
            'deferred-for-category-3-topStoryAuthor1',
            ['categories', 0, 'topStory', 'title'],
            ['categories', 0, 'topStory', 'author'],
            'deferred-for-category-1-topStoryAuthor2',
            ['categories', 1, 'topStory', 'title'],
            ['categories', 1, 'topStory', 'author'],
            'deferred-for-category-2-topStoryAuthor2',
            ['categories', 2, 'topStory', 'title'],
            ['categories', 2, 'topStory', 'author'],
            'deferred-for-category-3-topStoryAuthor2',
            'deferred-for-story-8-author',
            'deferred-for-story-3-author',
            'deferred-for-story-9-author',
            ['categories', 0, 'topStory', 'author', 'name'],
            ['categories', 0, 'topStoryAuthor', 'name'],
            ['categories', 1, 'topStory', 'author', 'name'],
            ['categories', 1, 'topStoryAuthor', 'name'],
            ['categories', 2, 'topStory', 'author', 'name'],
            ['categories', 2, 'topStoryAuthor', 'name'],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function findStoryById(int $id): ?array
    {
        foreach ($this->storyDataSource as $story) {
            if ($story['id'] === $id) {
                return $story;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function findUserById(int $id): ?array
    {
        foreach ($this->userDataSource as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $expectedPaths
     *
     * @throws \JsonException
     */
    private function assertPathsMatch(array $expectedPaths): void
    {
        self::assertCount(count($expectedPaths), $this->paths);
        foreach ($expectedPaths as $expectedPath) {
            self::assertContains($expectedPath, $this->paths, 'Missing path: ' . json_encode($expectedPath, JSON_THROW_ON_ERROR));
        }
    }

    /**
     * Test that DataLoader-style batching works correctly with many Deferred objects.
     *
     * DataLoaders work by:
     * 1. Collecting all entity IDs during field resolution (buffering)
     * 2. Making a single batch query when the queue is processed
     * 3. Distributing results to waiting promises
     *
     * This test verifies that all IDs are collected before any batch is executed,
     * which is essential for the DataLoader pattern to work correctly.
     *
     * The test creates 600 items to exceed any reasonable batch size threshold,
     * ensuring that incremental processing (if implemented) doesn't break batching.
     *
     * @see https://github.com/webonyx/graphql-php/issues/1803
     * @see https://github.com/webonyx/graphql-php/issues/972
     */
    public function testDataLoaderPatternWithManyDeferredObjects(): void
    {
        $itemCount = 600;

        // Simulate a data source (like a database)
        $authorData = [];
        for ($i = 1; $i <= 100; ++$i) {
            $authorData[$i] = ['id' => $i, 'name' => "Author {$i}"];
        }

        $bookData = [];
        for ($i = 1; $i <= $itemCount; ++$i) {
            $bookData[$i] = [
                'id' => $i,
                'title' => "Book {$i}",
                'authorId' => ($i % 100) + 1,
            ];
        }

        // DataLoader simulation - buffers IDs and loads in batch
        $authorBuffer = [];
        $loadedAuthors = [];
        $batchLoadCount = 0;

        $loadAuthor = function (int $authorId) use (&$authorBuffer, &$loadedAuthors, &$batchLoadCount, $authorData): Deferred {
            $authorBuffer[] = $authorId;

            return new Deferred(static function () use ($authorId, &$authorBuffer, &$loadedAuthors, &$batchLoadCount, $authorData): ?array {
                // On first execution, batch load all buffered IDs
                if ($authorBuffer !== []) {
                    ++$batchLoadCount;
                    // Simulate batch database query: load all buffered authors at once
                    foreach ($authorBuffer as $id) {
                        $loadedAuthors[$id] = $authorData[$id] ?? null;
                    }
                    $authorBuffer = []; // Clear buffer after batch load
                }

                return $loadedAuthors[$authorId] ?? null;
            });
        };

        $authorType = new ObjectType([
            'name' => 'Author',
            'fields' => [
                'id' => Type::int(),
                'name' => Type::string(),
            ],
        ]);

        $bookType = new ObjectType([
            'name' => 'Book',
            'fields' => [
                'id' => Type::int(),
                'title' => Type::string(),
                'author' => [
                    'type' => $authorType,
                    'resolve' => static fn (array $book) => $loadAuthor($book['authorId']),
                ],
            ],
        ]);

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'books' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($bookType))),
                    'resolve' => static fn (): array => array_values($bookData),
                ],
            ],
        ]);

        $schema = new Schema(['query' => $queryType]);
        $query = Parser::parse('{ books { id title author { id name } } }');

        $result = Executor::execute($schema, $query);
        $resultArray = $result->toArray();

        // Verify no errors
        self::assertArrayNotHasKey('errors', $resultArray, 'Query should not produce errors');

        // Verify all books are returned
        self::assertCount($itemCount, $resultArray['data']['books']);

        // Verify no null authors (this was the bug in issue #1803)
        $nullAuthorCount = 0;
        foreach ($resultArray['data']['books'] as $index => $book) {
            if ($book['author'] === null) {
                ++$nullAuthorCount;
            }
        }

        self::assertSame(
            0,
            $nullAuthorCount,
            "Expected 0 null authors, but found {$nullAuthorCount}. "
            . 'This indicates that batch loading was triggered before all IDs were collected.'
        );

        // Verify batch loading happened only once (DataLoader pattern)
        // With proper batching, all 600 author loads should be batched into ONE query
        self::assertSame(
            1,
            $batchLoadCount,
            "Expected exactly 1 batch load, but got {$batchLoadCount}. "
            . 'Multiple batch loads indicate that the queue was processed before all Deferred objects were created.'
        );
    }

    /**
     * Test nested Deferred fields with DataLoader pattern.
     *
     * Reproduces the bug from issue #1803 where nested lists returned null
     * when using DataLoader-style batching with many items.
     *
     * The scenario: eventDays -> event -> eventDays
     * Each level uses Deferred with buffered batch loading.
     *
     * @see https://github.com/webonyx/graphql-php/issues/1803
     */
    public function testNestedDataLoaderPattern(): void
    {
        $eventCount = 50;
        $daysPerEvent = 15; // Total: 50 * 15 = 750 items

        // Data sources
        $events = [];
        for ($i = 1; $i <= $eventCount; ++$i) {
            $events[$i] = ['id' => $i, 'name' => "Event {$i}"];
        }

        $eventDays = [];
        $dayId = 1;
        for ($eventId = 1; $eventId <= $eventCount; ++$eventId) {
            for ($day = 1; $day <= $daysPerEvent; ++$day) {
                $eventDays[$dayId] = [
                    'id' => $dayId,
                    'eventId' => $eventId,
                    'date' => '2025-01-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                ];
                ++$dayId;
            }
        }

        // DataLoader for events
        $eventBuffer = [];
        $loadedEvents = [];
        $eventBatchCount = 0;

        $loadEvent = function (int $eventId) use (&$eventBuffer, &$loadedEvents, &$eventBatchCount, $events): Deferred {
            $eventBuffer[] = $eventId;

            return new Deferred(static function () use ($eventId, &$eventBuffer, &$loadedEvents, &$eventBatchCount, $events): ?array {
                if ($eventBuffer !== []) {
                    ++$eventBatchCount;
                    foreach ($eventBuffer as $id) {
                        $loadedEvents[$id] = $events[$id] ?? null;
                    }
                    $eventBuffer = [];
                }

                return $loadedEvents[$eventId] ?? null;
            });
        };

        // DataLoader for event days by event ID
        $eventDaysBuffer = [];
        $loadedEventDays = [];
        $eventDaysBatchCount = 0;

        $loadEventDays = function (int $eventId) use (&$eventDaysBuffer, &$loadedEventDays, &$eventDaysBatchCount, $eventDays): Deferred {
            $eventDaysBuffer[] = $eventId;

            return new Deferred(static function () use ($eventId, &$eventDaysBuffer, &$loadedEventDays, &$eventDaysBatchCount, $eventDays): array {
                if ($eventDaysBuffer !== []) {
                    ++$eventDaysBatchCount;
                    // Batch load: group event days by event ID
                    foreach ($eventDaysBuffer as $eId) {
                        $loadedEventDays[$eId] = array_values(array_filter(
                            $eventDays,
                            static fn (array $day): bool => $day['eventId'] === $eId
                        ));
                    }
                    $eventDaysBuffer = [];
                }

                return $loadedEventDays[$eventId] ?? [];
            });
        };

        // Use by-reference to handle circular type dependencies
        $eventType = null;

        $eventDayType = new ObjectType([
            'name' => 'EventDay',
            'fields' => function () use (&$eventType, $loadEvent): array {
                return [
                    'id' => Type::int(),
                    'date' => Type::string(),
                    'event' => [
                        'type' => $eventType,
                        'resolve' => static fn (array $day) => $loadEvent($day['eventId']),
                    ],
                ];
            },
        ]);

        $eventType = new ObjectType([
            'name' => 'Event',
            'fields' => fn (): array => [
                'id' => Type::int(),
                'name' => Type::string(),
                'eventDays' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($eventDayType))),
                    'resolve' => static fn (array $event) => $loadEventDays($event['id']),
                ],
            ],
        ]);

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'eventDays' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($eventDayType))),
                    'resolve' => static fn (): array => array_values($eventDays),
                ],
            ],
        ]);

        $schema = new Schema(['query' => $queryType]);

        // Query that mirrors the bug report: eventDays -> event -> eventDays
        $query = Parser::parse('
            {
                eventDays {
                    id
                    date
                    event {
                        id
                        name
                        eventDays {
                            id
                            date
                        }
                    }
                }
            }
        ');

        $result = Executor::execute($schema, $query);
        $resultArray = $result->toArray();

        // Verify no errors
        self::assertArrayNotHasKey('errors', $resultArray, 'Query should not produce errors');

        $totalDays = $eventCount * $daysPerEvent;
        self::assertCount($totalDays, $resultArray['data']['eventDays']);

        // Check for null events (the bug from issue #1803)
        $nullEventCount = 0;
        $nullNestedDaysCount = 0;

        foreach ($resultArray['data']['eventDays'] as $day) {
            if ($day['event'] === null) {
                ++$nullEventCount;
            } elseif ($day['event']['eventDays'] === null) {
                ++$nullNestedDaysCount;
            }
        }

        self::assertSame(
            0,
            $nullEventCount,
            "Expected 0 null events, but found {$nullEventCount}. "
            . 'This indicates premature batch processing broke DataLoader batching.'
        );

        self::assertSame(
            0,
            $nullNestedDaysCount,
            "Expected 0 null nested eventDays, but found {$nullNestedDaysCount}. "
            . 'This indicates premature batch processing broke nested DataLoader batching.'
        );
    }
}
