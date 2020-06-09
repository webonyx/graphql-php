<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Deferred;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;
use function count;
use function in_array;
use function json_encode;

class DeferredFieldsTest extends TestCase
{
    /** @var ObjectType */
    private $userType;

    /** @var ObjectType */
    private $storyType;

    /** @var ObjectType */
    private $categoryType;

    /** @var mixed */
    private $paths;

    /** @var mixed[][] */
    private $storyDataSource;

    /** @var mixed[][] */
    private $userDataSource;

    /** @var mixed[][] */
    private $categoryDataSource;

    /** @var ObjectType */
    private $queryType;

    public function setUp() : void
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
            ['id' => 4, 'name' => 'Dirk', 'bestFriend' => 1],
        ];

        $this->categoryDataSource = [
            ['id' => 1, 'name' => 'Category #1', 'topStoryId' => 8],
            ['id' => 2, 'name' => 'Category #2', 'topStoryId' => 3],
            ['id' => 3, 'name' => 'Category #3', 'topStoryId' => 9],
        ];

        $this->paths    = [];
        $this->userType = new ObjectType([
            'name'   => 'User',
            'fields' => function () {
                return [
                    'name'       => [
                        'type'    => Type::string(),
                        'resolve' => function ($user, $args, $context, ResolveInfo $info) {
                            $this->paths[] = $info->path;

                            return $user['name'];
                        },
                    ],
                    'bestFriend' => [
                        'type'    => $this->userType,
                        'resolve' => function ($user, $args, $context, ResolveInfo $info) {
                            $this->paths[] = $info->path;

                            return new Deferred(function () use ($user) {
                                $this->paths[] = 'deferred-for-best-friend-of-' . $user['id'];

                                return $this->findUserById($user['bestFriendId']);
                            });
                        },
                    ],
                ];
            },
        ]);

        $this->storyType = new ObjectType([
            'name'   => 'Story',
            'fields' => [
                'title'  => [
                    'type'    => Type::string(),
                    'resolve' => function ($story, $args, $context, ResolveInfo $info) {
                        $this->paths[] = $info->path;

                        return $story['title'];
                    },
                ],
                'author' => [
                    'type'    => $this->userType,
                    'resolve' => function ($story, $args, $context, ResolveInfo $info) {
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
            'name'   => 'Category',
            'fields' => [
                'name' => [
                    'type'    => Type::string(),
                    'resolve' => function ($category, $args, $context, ResolveInfo $info) {
                        $this->paths[] = $info->path;

                        return $category['name'];
                    },
                ],

                'stories'  => [
                    'type'    => Type::listOf($this->storyType),
                    'resolve' => function ($category, $args, $context, ResolveInfo $info) {
                        $this->paths[] = $info->path;

                        return Utils::filter(
                            $this->storyDataSource,
                            static function ($story) use ($category) : bool {
                                return in_array($category['id'], $story['categoryIds'], true);
                            }
                        );
                    },
                ],
                'topStory' => [
                    'type'    => $this->storyType,
                    'resolve' => function ($category, $args, $context, ResolveInfo $info) {
                        $this->paths[] = $info->path;

                        return new Deferred(function () use ($category) {
                            $this->paths[] = 'deferred-for-category-' . $category['id'] . '-topStory';

                            return $this->findStoryById($category['topStoryId']);
                        });
                    },
                ],
                'topStoryAuthor' => [
                    'type' => $this->userType,
                    'resolve' => function ($category, $args, $context, ResolveInfo $info) {
                        $this->paths[] = $info->path;

                        return new Deferred(function () use ($category) {
                            $this->paths[] = 'deferred-for-category-' . $category['id'] . '-topStoryAuthor1';
                            $story         = $this->findStoryById($category['topStoryId']);

                            return new Deferred(function () use ($category, $story) {
                                $this->paths[] = 'deferred-for-category-' . $category['id'] . '-topStoryAuthor2';

                                return $this->findUserById($story['authorId']);
                            });
                        });
                    },
                ],
            ],
        ]);

        $this->queryType = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'topStories'       => [
                    'type'    => Type::listOf($this->storyType),
                    'resolve' => function ($rootValue, $args, $context, ResolveInfo $info) {
                        $this->paths[] = $info->path;

                        return Utils::filter(
                            $this->storyDataSource,
                            static function ($story) : bool {
                                return $story['id'] % 2 === 1;
                            }
                        );
                    },
                ],
                'featuredCategory' => [
                    'type'    => $this->categoryType,
                    'resolve' => function ($rootValue, $args, $context, ResolveInfo $info) : array {
                        $this->paths[] = $info->path;

                        return $this->categoryDataSource[0];
                    },
                ],
                'categories'       => [
                    'type'    => Type::listOf($this->categoryType),
                    'resolve' => function ($rootValue, $args, $context, ResolveInfo $info) : array {
                        $this->paths[] = $info->path;

                        return $this->categoryDataSource;
                    },
                ],
            ],
        ]);

        parent::setUp();
    }

    public function testDeferredFields() : void
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
                'topStories'       => [
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
        self::assertEquals($expected, $result->toArray());

        $expectedPaths = [
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
        ];
        self::assertCount(count($expectedPaths), $this->paths);
        foreach ($expectedPaths as $expectedPath) {
            self::assertTrue(in_array($expectedPath, $this->paths, true), 'Missing path: ' . json_encode($expectedPath));
        }
    }

    public function testNestedDeferredFields() : void
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
        $author4 = ['name' => 'Dirk', 'bestFriend' => ['name' => 'John']];

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
        self::assertEquals($expected, $result->toArray());

        $expectedPaths = [
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
        ];
        self::assertCount(count($expectedPaths), $this->paths);
        foreach ($expectedPaths as $expectedPath) {
            self::assertTrue(in_array($expectedPath, $this->paths, true), 'Missing path: ' . json_encode($expectedPath));
        }
    }

    public function testComplexRecursiveDeferredFields() : void
    {
        $complexType = new ObjectType([
            'name'   => 'ComplexType',
            'fields' => function () use (&$complexType) {
                return [
                    'sync'         => [
                        'type'    => Type::string(),
                        'resolve' => function ($complexType, $args, $context, ResolveInfo $info) : string {
                            $this->paths[] = $info->path;

                            return 'sync';
                        },
                    ],
                    'deferred'     => [
                        'type'    => Type::string(),
                        'resolve' => function ($complexType, $args, $context, ResolveInfo $info) {
                            $this->paths[] = $info->path;

                            return new Deferred(function () use ($info) : string {
                                $this->paths[] = ['!dfd for: ', $info->path];

                                return 'deferred';
                            });
                        },
                    ],
                    'nest'         => [
                        'type'    => $complexType,
                        'resolve' => function ($complexType, $args, $context, ResolveInfo $info) : array {
                            $this->paths[] = $info->path;

                            return [];
                        },
                    ],
                    'deferredNest' => [
                        'type'    => $complexType,
                        'resolve' => function ($complexType, $args, $context, ResolveInfo $info) {
                            $this->paths[] = $info->path;

                            return new Deferred(function () use ($info) : array {
                                $this->paths[] = ['!dfd nest for: ', $info->path];

                                return [];
                            });
                        },
                    ],
                ];
            },
        ]);

        $schema = new Schema(['query' => $complexType]);

        $query    = Parser::parse('
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
        $result   = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'nest'         => [
                    'sync'         => 'sync',
                    'deferred'     => 'deferred',
                    'nest'         => [
                        'sync'     => 'sync',
                        'deferred' => 'deferred',
                    ],
                    'deferredNest' => [
                        'sync'     => 'sync',
                        'deferred' => 'deferred',
                    ],
                ],
                'deferredNest' => [
                    'sync'         => 'sync',
                    'deferred'     => 'deferred',
                    'nest'         => [
                        'sync'     => 'sync',
                        'deferred' => 'deferred',
                    ],
                    'deferredNest' => [
                        'sync'     => 'sync',
                        'deferred' => 'deferred',
                    ],
                ],
            ],
        ];

        self::assertEquals($expected, $result->toArray());

        $expectedPaths = [
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
        ];

        // Note: not using self::assertEquals() because coroutineExecutor has a different sequence of calls
        self::assertCount(count($expectedPaths), $this->paths);
        foreach ($expectedPaths as $expectedPath) {
            self::assertTrue(in_array($expectedPath, $this->paths, true), 'Missing path: ' . json_encode($expectedPath));
        }
    }

    public function testDeferredChaining()
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

        $author1 = ['name' => 'John'/*, 'bestFriend' => ['name' => 'Dirk']*/];
        $author2 = ['name' => 'Jane'/*, 'bestFriend' => ['name' => 'Joe']*/];
        $author3 = ['name' => 'Joe'/*, 'bestFriend' => ['name' => 'Jane']*/];
        $author4 = ['name' => 'Dirk'/*, 'bestFriend' => ['name' => 'John']*/];

        $story1 = ['title' => 'Story #8', 'author' => $author1];
        $story2 = ['title' => 'Story #3', 'author' => $author3];
        $story3 = ['title' => 'Story #9', 'author' => $author2];

        $result   = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'categories' => [
                    ['name' => 'Category #1', 'topStory' => $story1, 'topStoryAuthor' => $author1],
                    ['name' => 'Category #2', 'topStory' => $story2, 'topStoryAuthor' => $author3],
                    ['name' => 'Category #3', 'topStory' => $story3, 'topStoryAuthor' => $author2],
                ],
            ],
        ];
        self::assertEquals($expected, $result->toArray());

        $expectedPaths = [
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
        ];
        self::assertEquals($expectedPaths, $this->paths);
    }

    private function findStoryById($id)
    {
        return Utils::find(
            $this->storyDataSource,
            static function ($story) use ($id) : bool {
                return $story['id'] === $id;
            }
        );
    }

    private function findUserById($id)
    {
        return Utils::find(
            $this->userDataSource,
            static function ($user) use ($id) : bool {
                return $user['id'] === $id;
            }
        );
    }
}
