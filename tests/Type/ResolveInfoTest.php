<?php
namespace GraphQL\Tests\Type;

use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class ResolveInfoTest extends \PHPUnit_Framework_TestCase
{
    public function testFieldSelection()
    {
        $image = new ObjectType([
            'name' => 'Image',
            'fields' => [
                'url' => ['type' => Type::string()],
                'width' => ['type' => Type::int()],
                'height' => ['type' => Type::int()]
            ]
        ]);

        $article = null;

        $author = new ObjectType([
            'name' => 'Author',
            'fields' => function() use ($image, &$article) {
                return [
                    'id' => ['type' => Type::string()],
                    'name' => ['type' => Type::string()],
                    'pic' => [ 'type' => $image, 'args' => [
                        'width' => ['type' => Type::int()],
                        'height' => ['type' => Type::int()]
                    ]],
                    'recentArticle' => ['type' => $article],
                ];
            },
        ]);

        $reply = new ObjectType([
            'name' => 'Reply',
            'fields' => [
                'author' => ['type' => $author],
                'body' => ['type' => Type::string()]
            ]
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
                'replies' => ['type' => Type::listOf($reply)]
            ]
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
            'replies' => true
        ];
        $expectedDeepSelection = [
            'author' => [
                'name' => true,
                'pic' => [
                    'url' => true,
                    'width' => true
                ]
            ],
            'image' => [
                'width' => true,
                'height' => true,
                'url' => true
            ],
            'replies' => [
                'body' => true,
                'author' => [
                    'id' => true,
                    'name' => true,
                    'pic' => [
                        'url' => true,
                        'width' => true,
                        'height' => true
                    ],
                    'recentArticle' => [
                        'id' => true,
                        'title' => true,
                        'body' => true
                    ]
                ]
            ]
        ];

        $hasCalled = false;
        $actualDefaultSelection = null;
        $actualDeepSelection = null;

        $blogQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'article' => [
                    'type' => $article,
                    'resolve' => function($value, $args, $context, ResolveInfo $info) use (&$hasCalled, &$actualDefaultSelection, &$actualDeepSelection) {
                        $hasCalled = true;
                        $actualDefaultSelection = $info->getFieldSelection();
                        $actualDeepSelection = $info->getFieldSelection(5);
                        return null;
                    }
                ]
            ]
        ]);

        $schema = new Schema(['query' => $blogQuery]);
        $result = GraphQL::execute($schema, $doc);

        $this->assertTrue($hasCalled);
        $this->assertEquals(['data' => ['article' => null]], $result);
        $this->assertEquals($expectedDefaultSelection, $actualDefaultSelection);
        $this->assertEquals($expectedDeepSelection, $actualDeepSelection);
    }

    public function testMergedFragmentsFieldSelection()
    {
        $image = new ObjectType([
            'name' => 'Image',
            'fields' => [
                'url' => ['type' => Type::string()],
                'width' => ['type' => Type::int()],
                'height' => ['type' => Type::int()]
            ]
        ]);

        $article = null;

        $author = new ObjectType([
            'name' => 'Author',
            'fields' => function() use ($image, &$article) {
                return [
                    'id' => ['type' => Type::string()],
                    'name' => ['type' => Type::string()],
                    'pic' => [ 'type' => $image, 'args' => [
                        'width' => ['type' => Type::int()],
                        'height' => ['type' => Type::int()]
                    ]],
                    'recentArticle' => ['type' => $article],
                ];
            },
        ]);

        $reply = new ObjectType([
            'name' => 'Reply',
            'fields' => [
                'author' => ['type' => $author],
                'body' => ['type' => Type::string()]
            ]
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
                'replies' => ['type' => Type::listOf($reply)]
            ]
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
                    'width' => true
                ]
            ],
            'image' => [
                'width' => true,
                'height' => true,
                'url' => true
            ],
            'replies' => [
                'body' => true, //this would be missing if not for the fix https://github.com/webonyx/graphql-php/pull/98
                'author' => [
                    'id' => true,
                    'name' => true,
                    'pic' => [
                        'url' => true,
                        'width' => true,
                        'height' => true
                    ],
                    'recentArticle' => [
                        'id' => true,
                        'title' => true,
                        'body' => true
                    ]
                ]
            ]
        ];

        $hasCalled = false;
        $actualDefaultSelection = null;
        $actualDeepSelection = null;

        $blogQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'article' => [
                    'type' => $article,
                    'resolve' => function($value, $args, $context, ResolveInfo $info) use (&$hasCalled, &$actualDeepSelection) {
                        $hasCalled = true;
                        $actualDeepSelection = $info->getFieldSelection(5);
                        return null;
                    }
                ]
            ]
        ]);

        $schema = new Schema(['query' => $blogQuery]);
        $result = GraphQL::execute($schema, $doc);

        $this->assertTrue($hasCalled);
        $this->assertEquals(['data' => ['article' => null]], $result);
        $this->assertEquals($expectedDeepSelection, $actualDeepSelection);
    }


}
