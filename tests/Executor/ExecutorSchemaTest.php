<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

/**
 * describe('Execute: Handles execution with a complex schema', () => {.
 */
class ExecutorSchemaTest extends TestCase
{
    /**
     * @see it('executes using a schema')
     */
    public function testExecutesUsingASchema(): void
    {
        $BlogSerializableValueType = new CustomScalarType([
            'name' => 'JsonSerializableValueScalar',
            'serialize' => static fn ($value) => $value,
        ]);

        $BlogArticle = null;
        $BlogImage = new ObjectType([
            'name' => 'Image',
            'fields' => [
                'url' => ['type' => Type::string()],
                'width' => ['type' => Type::int()],
                'height' => ['type' => Type::int()],
            ],
        ]);

        $BlogAuthor = new ObjectType([
            'name' => 'Author',
            'fields' => static function () use (&$BlogArticle, &$BlogImage): array {
                return [
                    'id' => ['type' => Type::string()],
                    'name' => ['type' => Type::string()],
                    'pic' => [
                        'args' => [
                            'width' => ['type' => Type::int()],
                            'height' => ['type' => Type::int()],
                        ],
                        'type' => $BlogImage,
                        'resolve' => static fn (array $obj, array $args) => $obj['pic']($args['width'], $args['height']),
                    ],
                    'recentArticle' => $BlogArticle,
                ];
            },
        ]);

        $BlogArticle = new ObjectType([
            'name' => 'Article',
            'fields' => [
                'id' => ['type' => Type::nonNull(Type::string())],
                'isPublished' => ['type' => Type::boolean()],
                'author' => ['type' => $BlogAuthor],
                'title' => ['type' => Type::string()],
                'body' => ['type' => Type::string()],
                'keywords' => ['type' => Type::listOf(Type::string())],
                'meta' => ['type' => $BlogSerializableValueType],
            ],
        ]);

        $BlogQuery = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'article' => [
                    'type' => $BlogArticle,
                    'args' => ['id' => ['type' => Type::id()]],
                    'resolve' => fn ($rootValue, array $args): array => $this->article($args['id']),
                ],
                'feed' => [
                    'type' => Type::listOf($BlogArticle),
                    'resolve' => fn (): array => [
                        $this->article('1'),
                        $this->article('2'),
                        $this->article('3'),
                        $this->article('4'),
                        $this->article('5'),
                        $this->article('6'),
                        $this->article('7'),
                        $this->article('8'),
                        $this->article('9'),
                        $this->article('10'),
                    ],
                ],
            ],
        ]);

        $BlogSchema = new Schema(['query' => $BlogQuery]);

        $request = '
      {
        feed {
          id,
          title
        },
        article(id: "1") {
          ...articleFields,
          author {
            id,
            name,
            pic(width: 640, height: 480) {
              url,
              width,
              height
            },
            recentArticle {
              ...articleFields,
              keywords
            }
          }
          meta
        }
      }

      fragment articleFields on Article {
        id,
        isPublished,
        title,
        body,
        hidden,
        notdefined
      }
    ';

        $expected = [
            'data' => [
                'feed' => [
                    [
                        'id' => '1',
                        'title' => 'My Article 1',
                    ],
                    [
                        'id' => '2',
                        'title' => 'My Article 2',
                    ],
                    [
                        'id' => '3',
                        'title' => 'My Article 3',
                    ],
                    [
                        'id' => '4',
                        'title' => 'My Article 4',
                    ],
                    [
                        'id' => '5',
                        'title' => 'My Article 5',
                    ],
                    [
                        'id' => '6',
                        'title' => 'My Article 6',
                    ],
                    [
                        'id' => '7',
                        'title' => 'My Article 7',
                    ],
                    [
                        'id' => '8',
                        'title' => 'My Article 8',
                    ],
                    [
                        'id' => '9',
                        'title' => 'My Article 9',
                    ],
                    [
                        'id' => '10',
                        'title' => 'My Article 10',
                    ],
                ],
                'article' => [
                    'id' => '1',
                    'isPublished' => true,
                    'title' => 'My Article 1',
                    'body' => 'This is a post',
                    'author' => [
                        'id' => '123',
                        'name' => 'John Smith',
                        'pic' => [
                            'url' => 'cdn://123',
                            'width' => 640,
                            'height' => 480,
                        ],
                        'recentArticle' => [
                            'id' => '1',
                            'isPublished' => true,
                            'title' => 'My Article 1',
                            'body' => 'This is a post',
                            'keywords' => ['foo', 'bar', '1', '1', null],
                        ],
                    ],
                    'meta' => ['title' => 'My Article 1 | My Blog'],
                ],
            ],
        ];

        self::assertEquals($expected, Executor::execute($BlogSchema, Parser::parse($request))->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function article(string $id): array
    {
        $johnSmith = null;
        $article = static function (string $id) use (&$johnSmith): array {
            return [
                'id' => $id,
                'isPublished' => 'true',
                'author' => $johnSmith,
                'title' => 'My Article ' . $id,
                'body' => 'This is a post',
                'hidden' => 'This data is not exposed in the schema',
                'keywords' => ['foo', 'bar', 1, true, null],
                'meta' => ['title' => 'My Article 1 | My Blog'],
            ];
        };

        $getPic = static fn (int $uid, int $width, int $height): array => [
            'url' => "cdn://{$uid}",
            'width' => $width,
            'height' => $height,
        ];

        $johnSmith = [
            'id' => 123,
            'name' => 'John Smith',
            'pic' => static fn (int $width, int $height): array => $getPic(123, $width, $height),
            'recentArticle' => $article('1'),
        ];

        return $article($id);
    }
}
