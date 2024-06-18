<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\GraphQL;
use GraphQL\Tests\Executor\TestClasses\Dog;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\QueryPlan;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class QueryPlanTest extends TestCase
{
    public function testQueryPlan(): void
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
            __typename
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
            'author' => [
                'type' => $author,
                'args' => [],
                'fields' => [
                    'name' => [
                        'type' => Type::string(),
                        'args' => [],
                        'fields' => [],
                    ],
                    'pic' => [
                        'type' => $image,
                        'args' => [
                            'width' => 100,
                            'height' => 200,
                        ],
                        'fields' => [
                            'url' => [
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
            'image' => [
                'type' => $image,
                'args' => [],
                'fields' => [
                    'url' => [
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
                    'body' => [
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
                            'pic' => [
                                'type' => $image,
                                'args' => [],
                                'fields' => [
                                    'url' => [
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

        /** @var QueryPlan|null $queryPlan */
        $queryPlan = null;

        $blogQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'article' => [
                    'type' => $article,
                    'resolve' => static function ($value, array $args, $context, ResolveInfo $info) use (&$queryPlan) {
                        $queryPlan = $info->lookAhead();

                        return null;
                    },
                ],
            ],
        ]);

        $schema = new Schema(['query' => $blogQuery]);
        $result = GraphQL::executeQuery($schema, $doc)->toArray();

        self::assertSame(['data' => ['article' => null]], $result);
        self::assertInstanceOf(QueryPlan::class, $queryPlan);
        self::assertEquals($expectedQueryPlan, $queryPlan->queryPlan());
        self::assertSame($expectedReferencedTypes, $queryPlan->getReferencedTypes());
        self::assertSame($expectedReferencedFields, $queryPlan->getReferencedFields());
        self::assertSame(['url', 'width', 'height'], $queryPlan->subFields('Image'));

        self::assertTrue($queryPlan->hasField('url'));
        self::assertFalse($queryPlan->hasField('test'));

        self::assertTrue($queryPlan->hasType('Image'));
        self::assertFalse($queryPlan->hasType('Test'));
    }

    public function testQueryPlanForWrappedTypes(): void
    {
        $article = new ObjectType([
            'name' => 'Article',
            'fields' => [
                'id' => ['type' => Type::string()],
            ],
        ]);

        $doc = '
      query Test {
        articles {
            id
        }
      }
';
        $expectedQueryPlan = [
            'id' => [
                'type' => Type::string(),
                'fields' => [],
                'args' => [],
            ],
        ];

        /** @var QueryPlan|null $queryPlan */
        $queryPlan = null;

        $blogQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'articles' => [
                    'type' => Type::nonNull(Type::listOf($article)),
                    'resolve' => static function ($value, array $args, $context, ResolveInfo $info) use (&$queryPlan): array {
                        $queryPlan = $info->lookAhead();

                        return [];
                    },
                ],
            ],
        ]);

        $schema = new Schema(['query' => $blogQuery]);
        GraphQL::executeQuery($schema, $doc);

        self::assertInstanceOf(QueryPlan::class, $queryPlan);
        self::assertSame($expectedQueryPlan, $queryPlan->queryPlan());
    }

    public function testQueryPlanOnInterface(): void
    {
        $petType = new InterfaceType([
            'name' => 'Pet',
            'fields' => static fn (): array => [
                'name' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $dogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => static fn ($obj): bool => $obj instanceof Dog,
            'fields' => static fn (): array => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
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
            'woofs' => [
                'type' => Type::boolean(),
                'fields' => [],
                'args' => [],
            ],
            'name' => [
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

        /** @var QueryPlan|null $queryPlan */
        $queryPlan = null;

        $petsQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'pets' => [
                    'type' => Type::listOf($petType),
                    'resolve' => static function ($value, array $args, $context, ResolveInfo $info) use (&$queryPlan): array {
                        $queryPlan = $info->lookAhead();

                        return [];
                    },
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => $petsQuery,
            'types' => [$dogType],
            'typeLoader' => static function (string $name) use ($dogType, $petType) {
                switch ($name) {
                    case 'Dog': return $dogType;
                    case 'Pet': return $petType;
                    default: throw new \Exception("Unexpected {$name}");
                }
            },
        ]);
        GraphQL::executeQuery($schema, $query)->toArray();

        self::assertInstanceOf(QueryPlan::class, $queryPlan);
        self::assertEquals($expectedQueryPlan, $queryPlan->queryPlan());
        self::assertSame($expectedReferencedTypes, $queryPlan->getReferencedTypes());
        self::assertSame($expectedReferencedFields, $queryPlan->getReferencedFields());
        self::assertSame(['woofs'], $queryPlan->subFields('Dog'));

        self::assertTrue($queryPlan->hasField('name'));
        self::assertFalse($queryPlan->hasField('test'));

        self::assertTrue($queryPlan->hasType('Dog'));
        self::assertFalse($queryPlan->hasType('Test'));
    }

    public function testQueryPlanTypenameOnUnion(): void
    {
        $dogType = new ObjectType([
            'name' => 'Dog',
            'isTypeOf' => static fn ($obj): bool => $obj instanceof Dog,
            'fields' => static fn (): array => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $petType = new UnionType([
            'name' => 'Pet',
            'types' => [$dogType],
        ]);

        /** @var QueryPlan|null $queryPlan */
        $queryPlan = null;
        $petsQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'pets' => [
                    'type' => Type::listOf($petType),
                    'resolve' => static function ($value, array $args, $context, ResolveInfo $info) use (&$queryPlan): array {
                        $queryPlan = $info->lookAhead();

                        return [];
                    },
                ],
            ],
        ]);

        $schema = new Schema(['query' => $petsQuery]);
        GraphQL::executeQuery($schema, /** @lang GraphQL */ '
        {
          pets {
            __typename
          }
        }
        ');

        self::assertInstanceOf(QueryPlan::class, $queryPlan);
        self::assertSame([], $queryPlan->queryPlan());
        self::assertSame(['Pet'], $queryPlan->getReferencedTypes());
        self::assertSame([], $queryPlan->getReferencedFields());
        self::assertSame([], $queryPlan->subFields('Dog'));

        // TODO really? maybe change in next major version
        self::assertFalse($queryPlan->hasField('__typename'));
        self::assertFalse($queryPlan->hasField('non-existent'));

        self::assertTrue($queryPlan->hasType('Pet'));
        self::assertFalse($queryPlan->hasType('Dog'));
        self::assertFalse($queryPlan->hasType('Non-Existent'));
    }

    public function testMergedFragmentsQueryPlan(): void
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
            'author' => [
                'type' => $author,
                'args' => [],
                'fields' => [
                    'name' => [
                        'type' => Type::string(),
                        'args' => [],
                        'fields' => [],
                    ],
                    'pic' => [
                        'type' => $image,
                        'args' => [
                            'width' => 100,
                            'height' => 200,
                        ],
                        'fields' => [
                            'url' => [
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
            'image' => [
                'type' => $image,
                'args' => [],
                'fields' => [
                    'url' => [
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
                    'body' => [
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
                            'pic' => [
                                'type' => $image,
                                'args' => [],
                                'fields' => [
                                    'url' => [
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
        self::assertSame(['data' => ['article' => null]], $result);
        self::assertEquals($expectedQueryPlan, $queryPlan->queryPlan());
        self::assertSame($expectedReferencedTypes, $queryPlan->getReferencedTypes());
        self::assertSame($expectedReferencedFields, $queryPlan->getReferencedFields());
        self::assertSame(['url', 'width', 'height'], $queryPlan->subFields('Image'));

        self::assertTrue($queryPlan->hasField('url'));
        self::assertFalse($queryPlan->hasField('test'));

        self::assertTrue($queryPlan->hasType('Image'));
        self::assertFalse($queryPlan->hasType('Test'));
    }

    public function testQueryPlanGroupingImplementorFieldsForAbstractTypes(): void
    {
        $car = null;

        $item = new InterfaceType([
            'name' => 'Item',
            'fields' => [
                'id' => Type::int(),
                'owner' => Type::string(),
            ],
            'resolveType' => static function () use (&$car) {
                return $car;
            },
        ]);

        $manualTransmission = new ObjectType([
            'name' => 'ManualTransmission',
            'fields' => [
                'speed' => Type::int(),
                'overdrive' => Type::boolean(),
            ],
        ]);

        $automaticTransmission = new ObjectType([
            'name' => 'AutomaticTransmission',
            'fields' => [
                'speed' => Type::int(),
                'sportMode' => Type::boolean(),
            ],
        ]);

        $transmission = new UnionType([
            'name' => 'Transmission',
            'types' => [$manualTransmission, $automaticTransmission],
            'resolveType' => static fn (): ObjectType => $manualTransmission,
        ]);

        $car = new ObjectType([
            'name' => 'Car',
            'fields' => [
                'id' => Type::int(),
                'owner' => Type::string(),
                'mark' => Type::string(),
                'model' => Type::string(),
                'transmission' => $transmission,
            ],
            'interfaces' => [$item],
        ]);

        $building = new ObjectType([
            'name' => 'Building',
            'fields' => [
                'id' => Type::int(),
                'owner' => Type::string(),
                'city' => Type::string(),
                'address' => Type::string(),
            ],
            'interfaces' => [$item],
        ]);

        $query = '{
            item {
                id
                owner
                ... on Car {
                    mark
                    model
                    transmission {
                        ... on ManualTransmission {
                            speed
                            overdrive
                        }
                        ... on AutomaticTransmission {
                            speed
                            sportMode
                        }
                    }
                }
                ... on Building {
                    city
                }
                ...BuildingFragment
            }
        }
        fragment BuildingFragment on Building {
            address
        }';

        $expectedResult = [
            'data' => ['item' => null],
        ];

        $expectedQueryPlan = [
            'fields' => [
                'id' => [
                    'type' => Type::int(),
                    'fields' => [],
                    'args' => [],
                ],
                'owner' => [
                    'type' => Type::string(),
                    'fields' => [],
                    'args' => [],
                ],
            ],
            'implementors' => [
                'Car' => [
                    'type' => $car,
                    'fields' => [
                        'mark' => [
                            'type' => Type::string(),
                            'fields' => [],
                            'args' => [],
                        ],
                        'model' => [
                            'type' => Type::string(),
                            'fields' => [],
                            'args' => [],
                        ],
                        'transmission' => [
                            'type' => $transmission,
                            'fields' => [],
                            'args' => [],
                            'implementors' => [
                                'ManualTransmission' => [
                                    'type' => $manualTransmission,
                                    'fields' => [
                                        'speed' => [
                                            'type' => Type::int(),
                                            'fields' => [],
                                            'args' => [],
                                        ],
                                        'overdrive' => [
                                            'type' => Type::boolean(),
                                            'fields' => [],
                                            'args' => [],
                                        ],
                                    ],
                                ],
                                'AutomaticTransmission' => [
                                    'type' => $automaticTransmission,
                                    'fields' => [
                                        'speed' => [
                                            'type' => Type::int(),
                                            'fields' => [],
                                            'args' => [],
                                        ],
                                        'sportMode' => [
                                            'type' => Type::boolean(),
                                            'fields' => [],
                                            'args' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'Building' => [
                    'type' => $building,
                    'fields' => [
                        'city' => [
                            'type' => Type::string(),
                            'fields' => [],
                            'args' => [],
                        ],
                        'address' => [
                            'type' => Type::string(),
                            'fields' => [],
                            'args' => [],
                        ],
                    ],
                ],
            ],
        ];

        $expectedReferencedTypes = ['ManualTransmission', 'AutomaticTransmission', 'Transmission', 'Car', 'Building', 'Item'];

        $expectedReferencedFields = ['speed', 'overdrive', 'sportMode', 'mark', 'model', 'transmission', 'city', 'address', 'id', 'owner'];

        $expectedItemSubFields = ['id', 'owner'];
        $expectedBuildingSubFields = ['city', 'address'];

        $hasCalled = false;
        /** @var QueryPlan $queryPlan */
        $queryPlan = null;

        $root = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'item' => [
                    'type' => $item,
                    'resolve' => static function ($value, array $args, $context, ResolveInfo $info) use (&$hasCalled, &$queryPlan) {
                        $hasCalled = true;
                        $queryPlan = $info->lookAhead([
                            'groupImplementorFields' => true,
                        ]);

                        return null;
                    },
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => $root,
            'types' => [$car, $building],
        ]);
        $result = GraphQL::executeQuery($schema, $query)->toArray();

        self::assertTrue($hasCalled);
        self::assertSame($expectedResult, $result);
        self::assertSame($expectedQueryPlan, $queryPlan->queryPlan());
        self::assertSame($expectedReferencedTypes, $queryPlan->getReferencedTypes());
        self::assertSame($expectedReferencedFields, $queryPlan->getReferencedFields());
        self::assertSame($expectedItemSubFields, $queryPlan->subFields('Item'));
        self::assertSame($expectedBuildingSubFields, $queryPlan->subFields('Building'));
    }

    public function testQueryPlanForMultipleFieldNodes(): void
    {
        /** @var ObjectType|null $entity */
        $entity = null;

        $subEntity = new ObjectType([
            'name' => 'SubEntity',
            'fields' => static function () use (&$entity): array {
                return [
                    'id' => ['type' => Type::string()],
                    'entity' => ['type' => $entity],
                ];
            },
        ]);

        $entity = new ObjectType([
            'name' => 'Entity',
            'fields' => [
                'subEntity' => ['type' => $subEntity],
            ],
        ]);

        $doc = /** @lang GraphQL */ <<<GRAPHQL
query Test {
    entity {
        subEntity {
            id
        }
    }
    entity {
        subEntity {
            id
        }
    }
}
GRAPHQL;

        $expectedQueryPlan = [
            'subEntity' => [
                'type' => $subEntity,
                'args' => [],
                'fields' => [
                    'id' => [
                        'type' => Type::string(),
                        'args' => [],
                        'fields' => [],
                    ],
                ],
            ],
        ];

        $hasCalled = false;
        /** @var QueryPlan|null $queryPlan */
        $queryPlan = null;

        $query = new ObjectType(
            [
                'name' => 'Query',
                'fields' => [
                    'entity' => [
                        'type' => $entity,
                        'resolve' => static function (
                            $value,
                            array $args,
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
            ]
        );

        $schema = new Schema(['query' => $query]);
        $result = GraphQL::executeQuery($schema, $doc)->toArray();

        self::assertTrue($hasCalled);
        self::assertSame(['data' => ['entity' => null]], $result);
        self::assertInstanceOf(QueryPlan::class, $queryPlan);
        self::assertEquals($expectedQueryPlan, $queryPlan->queryPlan());
    }
}
