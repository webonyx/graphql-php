<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\GraphQL;
use GraphQL\Tests\Executor\TestClasses\Dog;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\QueryPlan;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class QueryPlanTest extends TestCase
{
    public function testQueryPlan() : void
    {
        $image = new ObjectType([
            'name'   => 'Image',
            'fields' => [
                'url'    => ['type' => Type::string()],
                'width'  => ['type' => Type::int()],
                'height' => ['type' => Type::int()],
            ],
        ]);

        $article = null;

        $author = new ObjectType([
            'name'   => 'Author',
            'fields' => static function () use ($image, &$article) {
                return [
                    'id'            => ['type' => Type::string()],
                    'name'          => ['type' => Type::string()],
                    'pic'           => [
                        'type' => $image,
                        'args' => [
                            'width'  => ['type' => Type::int()],
                            'height' => ['type' => Type::int()],
                        ],
                    ],
                    'recentArticle' => ['type' => $article],
                ];
            },
        ]);

        $reply = new ObjectType([
            'name'   => 'Reply',
            'fields' => [
                'author' => ['type' => $author],
                'body'   => ['type' => Type::string()],
            ],
        ]);

        $article = new ObjectType([
            'name'   => 'Article',
            'fields' => [
                'id'          => ['type' => Type::string()],
                'isPublished' => ['type' => Type::boolean()],
                'author'      => ['type' => $author],
                'title'       => ['type' => Type::string()],
                'body'        => ['type' => Type::string()],
                'image'       => ['type' => $image],
                'replies'     => ['type' => Type::listOf($reply)],
            ],
        ]);

        $doc               = '
      query Test {
        article {
            author {
                name
                pic(width: 100, height: 200) {
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
        $expectedQueryPlan = [
            'author'  => [
                'type' => $author,
                'args' => [],
                'fields' => [
                    'name' => [
                        'type' => Type::string(),
                        'args' => [],
                        'fields' => [],
                    ],
                    'pic'  => [
                        'type' => $image,
                        'args' => [
                            'width' => 100,
                            'height' => 200,
                        ],
                        'fields' => [
                            'url'   => [
                                'type' => Type::string(),
                                'args' => [],
                                'fields' => [],
                            ],
                            'width' => [
                                'type' => Type::int(),
                                'args' => [],
                                'fields' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'image'   => [
                'type' => $image,
                'args' => [],
                'fields' => [
                    'url'   => [
                        'type' => Type::string(),
                        'args' => [],
                        'fields' => [],
                    ],
                    'width' => [
                        'type' => Type::int(),
                        'args' => [],
                        'fields' => [],
                    ],
                    'height' => [
                        'type' => Type::int(),
                        'args' => [],
                        'fields' => [],
                    ],
                ],
            ],
            'replies' => [
                'type' => Type::listOf($reply),
                'args' => [],
                'fields' => [
                    'body'   => [
                        'type' => Type::string(),
                        'args' => [],
                        'fields' => [],
                    ],
                    'author' => [
                        'type' => $author,
                        'args' => [],
                        'fields' => [
                            'id' => [
                                'type' => Type::string(),
                                'args' => [],
                                'fields' => [],
                            ],
                            'name' => [
                                'type' => Type::string(),
                                'args' => [],
                                'fields' => [],
                            ],
                            'pic'  => [
                                'type' => $image,
                                'args' => [],
                                'fields' => [
                                    'url'   => [
                                        'type' => Type::string(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                    'width' => [
                                        'type' => Type::int(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                    'height' => [
                                        'type' => Type::int(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                ],
                            ],
                            'recentArticle' => [
                                'type' => $article,
                                'args' => [],
                                'fields' => [
                                    'id' => [
                                        'type' => Type::string(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                    'title' => [
                                        'type' => Type::string(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                    'body' => [
                                        'type' => Type::string(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $expectedReferencedTypes = [
            'Image',
            'Author',
            'Article',
            'Reply',
        ];

        $expectedReferencedFields = [
            'url',
            'width',
            'height',
            'name',
            'pic',
            'id',
            'recentArticle',
            'title',
            'body',
            'author',
            'image',
            'replies',
        ];

        $hasCalled = false;
        /** @var QueryPlan $queryPlan */
        $queryPlan = null;

        $blogQuery = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'article' => [
                    'type'    => $article,
                    'resolve' => static function (
                        $value,
                        $args,
                        $context,
                        ResolveInfo $info
                    ) use (
                        &$hasCalled,
                        &$queryPlan
                    ) {
                        $hasCalled = true;
                        $queryPlan = $info->lookAhead();

                        return null;
                    },
                ],
            ],
        ]);

        $schema = new Schema(['query' => $blogQuery]);
        $result = GraphQL::executeQuery($schema, $doc)->toArray();

        self::assertTrue($hasCalled);
        self::assertEquals(['data' => ['article' => null]], $result);
        self::assertEquals($expectedQueryPlan, $queryPlan->queryPlan());
        self::assertEquals($expectedReferencedTypes, $queryPlan->getReferencedTypes());
        self::assertEquals($expectedReferencedFields, $queryPlan->getReferencedFields());
        self::assertEquals(['url', 'width', 'height'], $queryPlan->subFields('Image'));

        self::assertTrue($queryPlan->hasField('url'));
        self::assertFalse($queryPlan->hasField('test'));

        self::assertTrue($queryPlan->hasType('Image'));
        self::assertFalse($queryPlan->hasType('Test'));
    }

    public function testQueryPlanOnInterface() : void
    {
        $petType = new InterfaceType([
            'name'   => 'Pet',
            'fields' => static function () {
                return [
                    'name' => ['type' => Type::string()],
                ];
            },
        ]);

        $dogType = new ObjectType([
            'name'       => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf'   => static function ($obj) {
                return $obj instanceof Dog;
            },
            'fields' => static function () {
                return [
                    'name'  => ['type' => Type::string()],
                    'woofs' => ['type' => Type::boolean()],
                ];
            },
        ]);

        $query = 'query Test {
          pets {
            name
            ... on Dog {
              woofs
            }
          }
        }';

        $expectedQueryPlan = [
            'woofs'  => [
                'type' => Type::boolean(),
                'fields' => [],
                'args' => [],
            ],
            'name'   => [
                'type' => Type::string(),
                'args' => [],
                'fields' => [],
            ],
        ];

        $expectedReferencedTypes = [
            'Dog',
            'Pet',
        ];

        $expectedReferencedFields = [
            'woofs',
            'name',
        ];

        /** @var QueryPlan $queryPlan */
        $queryPlan = null;
        $hasCalled = false;

        $petsQuery = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'pets' => [
                    'type'    => Type::listOf($petType),
                    'resolve' => static function (
                        $value,
                        $args,
                        $context,
                        ResolveInfo $info
                    ) use (
                        &$hasCalled,
                        &$queryPlan
) {
                        $hasCalled = true;
                        $queryPlan = $info->lookAhead();

                        return [];
                    },
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => $petsQuery,
            'types'      => [$dogType],
            'typeLoader' => static function ($name) use ($dogType, $petType) {
                switch ($name) {
                    case 'Dog':
                        return $dogType;
                    case 'Pet':
                        return $petType;
                }
            },
        ]);
        $result = GraphQL::executeQuery($schema, $query)->toArray();

        self::assertTrue($hasCalled);
        self::assertEquals($expectedQueryPlan, $queryPlan->queryPlan());
        self::assertEquals($expectedReferencedTypes, $queryPlan->getReferencedTypes());
        self::assertEquals($expectedReferencedFields, $queryPlan->getReferencedFields());
        self::assertEquals(['woofs'], $queryPlan->subFields('Dog'));

        self::assertTrue($queryPlan->hasField('name'));
        self::assertFalse($queryPlan->hasField('test'));

        self::assertTrue($queryPlan->hasType('Dog'));
        self::assertFalse($queryPlan->hasType('Test'));
    }

    public function testMergedFragmentsQueryPlan() : void
    {
        $image = new ObjectType([
            'name'   => 'Image',
            'fields' => [
                'url'    => ['type' => Type::string()],
                'width'  => ['type' => Type::int()],
                'height' => ['type' => Type::int()],
            ],
        ]);

        $article = null;

        $author = new ObjectType([
            'name'   => 'Author',
            'fields' => static function () use ($image, &$article) {
                return [
                    'id'            => ['type' => Type::string()],
                    'name'          => ['type' => Type::string()],
                    'pic'           => [
                        'type' => $image,
                        'args' => [
                            'width'  => ['type' => Type::int()],
                            'height' => ['type' => Type::int()],
                        ],
                    ],
                    'recentArticle' => ['type' => $article],
                ];
            },
        ]);

        $reply = new ObjectType([
            'name'   => 'Reply',
            'fields' => [
                'author' => ['type' => $author],
                'body'   => ['type' => Type::string()],
            ],
        ]);

        $article = new ObjectType([
            'name'   => 'Article',
            'fields' => [
                'id'          => ['type' => Type::string()],
                'isPublished' => ['type' => Type::boolean()],
                'author'      => ['type' => $author],
                'title'       => ['type' => Type::string()],
                'body'        => ['type' => Type::string()],
                'image'       => ['type' => $image],
                'replies'     => ['type' => Type::listOf($reply)],
            ],
        ]);

        $doc = '
      query Test {
        article {
            author {
                name
                pic(width: 100, height: 200) {
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

        $expectedQueryPlan = [
            'author'  => [
                'type' => $author,
                'args' => [],
                'fields' => [
                    'name' => [
                        'type' => Type::string(),
                        'args' => [],
                        'fields' => [],
                    ],
                    'pic'  => [
                        'type' => $image,
                        'args' => [
                            'width' => 100,
                            'height' => 200,
                        ],
                        'fields' => [
                            'url'   => [
                                'type' => Type::string(),
                                'args' => [],
                                'fields' => [],
                            ],
                            'width' => [
                                'type' => Type::int(),
                                'args' => [],
                                'fields' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'image'   => [
                'type' => $image,
                'args' => [],
                'fields' => [
                    'url'   => [
                        'type' => Type::string(),
                        'args' => [],
                        'fields' => [],
                    ],
                    'width' => [
                        'type' => Type::int(),
                        'args' => [],
                        'fields' => [],
                    ],
                    'height' => [
                        'type' => Type::int(),
                        'args' => [],
                        'fields' => [],
                    ],
                ],
            ],
            'replies' => [
                'type' => Type::listOf($reply),
                'args' => [],
                'fields' => [
                    'body'   => [
                        'type' => Type::string(),
                        'args' => [],
                        'fields' => [],
                    ],
                    'author' => [
                        'type' => $author,
                        'args' => [],
                        'fields' => [
                            'id' => [
                                'type' => Type::string(),
                                'args' => [],
                                'fields' => [],
                            ],
                            'name' => [
                                'type' => Type::string(),
                                'args' => [],
                                'fields' => [],
                            ],
                            'pic'  => [
                                'type' => $image,
                                'args' => [],
                                'fields' => [
                                    'url'   => [
                                        'type' => Type::string(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                    'width' => [
                                        'type' => Type::int(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                    'height' => [
                                        'type' => Type::int(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                ],
                            ],
                            'recentArticle' => [
                                'type' => $article,
                                'args' => [],
                                'fields' => [
                                    'id' => [
                                        'type' => Type::string(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                    'title' => [
                                        'type' => Type::string(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                    'body' => [
                                        'type' => Type::string(),
                                        'args' => [],
                                        'fields' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $expectedReferencedTypes = [
            'Image',
            'Author',
            'Reply',
            'Article',
        ];

        $expectedReferencedFields = [
            'url',
            'width',
            'height',
            'name',
            'pic',
            'id',
            'recentArticle',
            'body',
            'author',
            'replies',
            'title',
            'image',
        ];

        $hasCalled = false;
        /** @var QueryPlan $queryPlan */
        $queryPlan = null;

        $blogQuery = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'article' => [
                    'type'    => $article,
                    'resolve' => static function (
                        $value,
                        $args,
                        $context,
                        ResolveInfo $info
                    ) use (
                        &$hasCalled,
                        &$queryPlan
                    ) {
                        $hasCalled = true;
                        $queryPlan = $info->lookAhead();

                        return null;
                    },
                ],
            ],
        ]);

        $schema = new Schema(['query' => $blogQuery]);
        $result = GraphQL::executeQuery($schema, $doc)->toArray();

        self::assertTrue($hasCalled);
        self::assertEquals(['data' => ['article' => null]], $result);
        self::assertEquals($expectedQueryPlan, $queryPlan->queryPlan());
        self::assertEquals($expectedReferencedTypes, $queryPlan->getReferencedTypes());
        self::assertEquals($expectedReferencedFields, $queryPlan->getReferencedFields());
        self::assertEquals(['url', 'width', 'height'], $queryPlan->subFields('Image'));

        self::assertTrue($queryPlan->hasField('url'));
        self::assertFalse($queryPlan->hasField('test'));

        self::assertTrue($queryPlan->hasType('Image'));
        self::assertFalse($queryPlan->hasType('Test'));
    }
}
