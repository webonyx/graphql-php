<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class ResolveInfoTest extends TestCase
{
    public function testFieldSelection(): void
    {
        $image = new ObjectType([
            'name' => 'Image',
            'fields' => [
                'url' => ['type' => Type::string()],
                'width' => ['type' => Type::int()],
                'height' => ['type' => Type::int()],
            ],
        ]);

        $article = null;

        $author = new ObjectType([
            'name' => 'Author',
            'fields' => static function () use ($image, &$article): array {
                return [
                    'id' => ['type' => Type::string()],
                    'name' => ['type' => Type::string()],
                    'pic' => [
                        'type' => $image,
                        'args' => [
                            'width' => ['type' => Type::int()],
                            'height' => ['type' => Type::int()],
                        ],
                    ],
                    'recentArticle' => ['type' => $article],
                ];
            },
        ]);

        $reply = new ObjectType([
            'name' => 'Reply',
            'fields' => [
                'author' => ['type' => $author],
                'body' => ['type' => Type::string()],
            ],
        ]);

        $article = new ObjectType([
            'name' => 'Article',
            'fields' => [
                'id' => ['type' => Type::string()],
                'isPublished' => ['type' => Type::boolean()],
                'author' => ['type' => $author],
                'title' => ['type' => Type::string()],
                'body' => ['type' => Type::string()],
                'image' => ['type' => $image],
                'replies' => ['type' => Type::listOf($reply)],
            ],
        ]);

        $doc = '
      query Test {
        article {
            author {
                name
                pic {
                    url
                    width
                }
            }
            image {
                width
                height
                ...MyImage
            }
            replies {
                body
                author {
                    id
                    name
                    pic {
                        url
                        width
                        ... on Image {
                            height
                        }
                    }
                    recentArticle {
                        id
                        title
                        body
                    }
                }
            }
        }
      }
      fragment MyImage on Image {
        url
      }
';
        $expectedDefaultSelection = [
            'author' => true,
            'image' => true,
            'replies' => true,
        ];
        $expectedDeepSelection = [
            'author' => [
                'name' => true,
                'pic' => [
                    'url' => true,
                    'width' => true,
                ],
            ],
            'image' => [
                'width' => true,
                'height' => true,
                'url' => true,
            ],
            'replies' => [
                'body' => true,
                'author' => [
                    'id' => true,
                    'name' => true,
                    'pic' => [
                        'url' => true,
                        'width' => true,
                        'height' => true,
                    ],
                    'recentArticle' => [
                        'id' => true,
                        'title' => true,
                        'body' => true,
                    ],
                ],
            ],
        ];

        $actualDefaultSelection = null;
        $actualDeepSelection = null;

        $blogQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'article' => [
                    'type' => $article,
                    'resolve' => static function (
                        $value,
                        array $args,
                        $context,
                        ResolveInfo $info
                    ) use (
                        &$actualDefaultSelection,
                        &$actualDeepSelection
                    ) {
                        $actualDefaultSelection = $info->getFieldSelection();
                        $actualDeepSelection = $info->getFieldSelection(5);

                        return null;
                    },
                ],
            ],
        ]);

        $schema = new Schema(['query' => $blogQuery]);
        $result = GraphQL::executeQuery($schema, $doc)->toArray();

        self::assertEquals(['data' => ['article' => null]], $result);
        self::assertEquals($expectedDefaultSelection, $actualDefaultSelection);
        self::assertEquals($expectedDeepSelection, $actualDeepSelection);
    }

    public function testFieldSelectionOnScalarTypes(): void
    {
        $query = '
            query Ping {
                ping
            }
        ';

        $pingPongQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'ping' => [
                    'type' => Type::string(),
                    'resolve' => static function ($value, array $args, $context, ResolveInfo $info): string {
                        self::assertSame([], $info->getFieldSelection());

                        return 'pong';
                    },
                ],
            ],
        ]);

        $schema = new Schema(['query' => $pingPongQuery]);
        $result = GraphQL::executeQuery($schema, $query)->toArray();

        self::assertSame(['data' => ['ping' => 'pong']], $result);
    }

    public function testMergedFragmentsFieldSelection(): void
    {
        $image = new ObjectType([
            'name' => 'Image',
            'fields' => [
                'url' => ['type' => Type::string()],
                'width' => ['type' => Type::int()],
                'height' => ['type' => Type::int()],
            ],
        ]);

        $article = null;

        $author = new ObjectType([
            'name' => 'Author',
            'fields' => static function () use ($image, &$article): array {
                return [
                    'id' => ['type' => Type::string()],
                    'name' => ['type' => Type::string()],
                    'pic' => [
                        'type' => $image,
                        'args' => [
                            'width' => ['type' => Type::int()],
                            'height' => ['type' => Type::int()],
                        ],
                    ],
                    'recentArticle' => ['type' => $article],
                ];
            },
        ]);

        $reply = new ObjectType([
            'name' => 'Reply',
            'fields' => [
                'author' => ['type' => $author],
                'body' => ['type' => Type::string()],
            ],
        ]);

        $article = new ObjectType([
            'name' => 'Article',
            'fields' => [
                'id' => ['type' => Type::string()],
                'isPublished' => ['type' => Type::boolean()],
                'author' => ['type' => $author],
                'title' => ['type' => Type::string()],
                'body' => ['type' => Type::string()],
                'image' => ['type' => $image],
                'replies' => ['type' => Type::listOf($reply)],
            ],
        ]);

        $doc = '
      query Test {
        article {
            author {
                name
                pic {
                    url
                    width
                }
            }
            image {
                width
                height
                ...MyImage
            }
            ...Replies01
            ...Replies02
        }
      }
      fragment MyImage on Image {
        url
      }

      fragment Replies01 on Article {
        _replies012: replies {
            body
        }
      }
      fragment Replies02 on Article {
        _replies012: replies {
            author {
                id
                name
                pic {
                    url
                    width
                    ... on Image {
                        height
                    }
                }
                recentArticle {
                    id
                    title
                    body
                }
            }
        }
       }
';

        $expectedDeepSelection = [
            'author' => [
                'name' => true,
                'pic' => [
                    'url' => true,
                    'width' => true,
                ],
            ],
            'image' => [
                'width' => true,
                'height' => true,
                'url' => true,
            ],
            'replies' => [
                'body' => true, // this would be missing if not for the fix https://github.com/webonyx/graphql-php/pull/98
                'author' => [
                    'id' => true,
                    'name' => true,
                    'pic' => [
                        'url' => true,
                        'width' => true,
                        'height' => true,
                    ],
                    'recentArticle' => [
                        'id' => true,
                        'title' => true,
                        'body' => true,
                    ],
                ],
            ],
        ];

        $hasCalled = false;
        $actualDeepSelection = null;

        $blogQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'article' => [
                    'type' => $article,
                    'resolve' => static function (
                        $value,
                        array $args,
                        $context,
                        ResolveInfo $info
                    ) use (
                        &$hasCalled,
                        &$actualDeepSelection
                    ) {
                        $hasCalled = true;
                        $actualDeepSelection = $info->getFieldSelection(5);

                        return null;
                    },
                ],
            ],
        ]);

        $schema = new Schema(['query' => $blogQuery]);
        $result = GraphQL::executeQuery($schema, $doc)->toArray();

        self::assertTrue($hasCalled);
        self::assertEquals(['data' => ['article' => null]], $result);
        self::assertEquals($expectedDeepSelection, $actualDeepSelection);
    }

    public function testDeepFieldSelectionOnDuplicatedFields(): void
    {
        $level2 = new ObjectType([
            'name' => 'level2',
            'fields' => [
                'scalar1' => ['type' => Type::int()],
                'scalar2' => ['type' => Type::int()],
            ],
        ]);
        $level1 = new ObjectType([
            'name' => 'level1',
            'fields' => [
                'scalar1' => ['type' => Type::int()],
                'scalar2' => ['type' => Type::int()],
                'level2' => $level2,
            ],
        ]);

        $hasCalled = false;
        $actualDeepSelection = null;

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'level1' => [
                    'type' => $level1,
                    'resolve' => static function (
                        $value,
                        array $args,
                        $context,
                        ResolveInfo $info
                    ) use (
                        &$hasCalled,
                        &$actualDeepSelection
                    ) {
                        $hasCalled = true;
                        $actualDeepSelection = $info->getFieldSelection(2);

                        return null;
                    },
                ],
            ],
        ]);

        $doc = '
        query deepMerge {
          level1 {
            level2 {
              scalar1
            }
            level2 {
              scalar2
            }
            scalar1
            scalar2
          }
        }
      ';

        $expectedDeepSelection = [
            'level2' => [
                'scalar1' => true,
                'scalar2' => true,
            ],
            'scalar1' => true,
            'scalar2' => true,
        ];

        $schema = new Schema(['query' => $query]);
        $result = GraphQL::executeQuery($schema, $doc)->toArray();

        self::assertTrue($hasCalled);
        self::assertEquals(['data' => ['level1' => null]], $result);
        self::assertEquals($expectedDeepSelection, $actualDeepSelection);
    }

    public function testPathAndUnaliasedPath(): void
    {
        $level2 = new ObjectType([
            'name' => 'level2',
            'fields' => [
                'scalar1' => [
                    'type' => Type::string(),
                    'resolve' => static function ($value, array $args, $context, ResolveInfo $info) {
                        return 'path: ' . implode('.', $info->path) . ', unaliasedPath: ' . implode('.', $info->unaliasedPath);
                    },
                ],
                'scalar2' => [
                    'type' => Type::string(),
                    'resolve' => static function ($value, array $args, $context, ResolveInfo $info) {
                        return 'path: ' . implode('.', $info->path) . ', unaliasedPath: ' . implode('.', $info->unaliasedPath);
                    },
                ],
            ],
        ]);
        $level1 = new ObjectType([
            'name' => 'level1',
            'fields' => [
                'level2' => [
                    'type' => $level2,
                    'resolve' => function () {
                        return true;
                    },
                ],
            ],
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'level1' => [
                    'type' => $level1,
                    'resolve' => function () {
                        return true;
                    },
                ],
            ],
        ]);

        $result = GraphQL::executeQuery(
            new Schema(['query' => $query]),
            <<<GRAPHQL
            query {
              level1 {
                level2 {
                  scalar1
                }
                level1000: level2 {
                  scalar2
                }
              }
            }
            GRAPHQL
        )->toArray();

        self::assertEquals([
            'data' => [
                'level1' => [
                    'level2' => [
                        'scalar1' => 'path: level1.level2.scalar1, unaliasedPath: level1.level2.scalar1',
                    ],
                    'level1000' => [
                        'scalar2' => 'path: level1.level1000.scalar2, unaliasedPath: level1.level2.scalar2',
                    ],
                ],
            ],
        ], $result);
    }
}
