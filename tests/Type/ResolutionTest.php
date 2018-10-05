<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use Exception;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\EagerResolution;
use GraphQL\Type\LazyResolution;
use PHPUnit\Framework\TestCase;
use function lcfirst;

class ResolutionTest extends TestCase
{
    /** @var ObjectType */
    private $query;

    /** @var ObjectType */
    private $mutation;

    /** @var InterfaceType */
    private $node;

    /** @var InterfaceType */
    private $content;

    /** @var ObjectType */
    private $blogStory;

    /** @var ObjectType */
    private $video;

    /** @var ObjectType */
    private $videoMetadata;

    /** @var ObjectType */
    private $comment;

    /** @var ObjectType */
    private $user;

    /** @var ObjectType */
    private $category;

    /** @var UnionType */
    private $mention;

    /** @var ObjectType */
    private $postStoryMutation;

    /** @var InputObjectType */
    private $postStoryMutationInput;

    /** @var ObjectType */
    private $postCommentMutation;

    /** @var InputObjectType */
    private $postCommentMutationInput;

    public function setUp()
    {
        $this->node = new InterfaceType([
            'name'   => 'Node',
            'fields' => [
                'id' => Type::string(),
            ],
        ]);

        $this->content = new InterfaceType([
            'name'   => 'Content',
            'fields' => function () {
                return [
                    'title'      => Type::string(),
                    'body'       => Type::string(),
                    'author'     => $this->user,
                    'comments'   => Type::listOf($this->comment),
                    'categories' => Type::listOf($this->category),
                ];
            },
        ]);

        $this->blogStory = new ObjectType([
            'name'       => 'BlogStory',
            'interfaces' => [
                $this->node,
                $this->content,
            ],
            'fields'     => function () {
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                    $this->content->getField('author'),
                    $this->content->getField('comments'),
                    $this->content->getField('categories'),
                ];
            },
        ]);

        new ObjectType([
            'name'       => 'Link',
            'interfaces' => [
                $this->node,
                $this->content,
            ],
            'fields'     => function () {
                return [
                    'id'         => $this->node->getField('id'),
                    'title'      => $this->content->getField('title'),
                    'body'       => $this->content->getField('body'),
                    'author'     => $this->content->getField('author'),
                    'comments'   => $this->content->getField('comments'),
                    'categories' => $this->content->getField('categories'),
                    'url'        => Type::string(),
                ];
            },

        ]);

        $this->videoMetadata = new ObjectType([
            'name'   => 'VideoMetadata',
            'fields' => [
                'lat' => Type::float(),
                'lng' => Type::float(),
            ],
        ]);

        $this->video = new ObjectType([
            'name'       => 'Video',
            'interfaces' => [
                $this->node,
                $this->content,
            ],
            'fields'     => function () {
                return [
                    'id'          => $this->node->getField('id'),
                    'title'       => $this->content->getField('title'),
                    'body'        => $this->content->getField('body'),
                    'author'      => $this->content->getField('author'),
                    'comments'    => $this->content->getField('comments'),
                    'categories'  => $this->content->getField('categories'),
                    'streamUrl'   => Type::string(),
                    'downloadUrl' => Type::string(),
                    'metadata'    => $this->videoMetadata,
                ];
            },
        ]);

        $this->comment = new ObjectType([
            'name'       => 'Comment',
            'interfaces' => [
                $this->node,
            ],
            'fields'     => function () {
                return [
                    'id'      => $this->node->getField('id'),
                    'author'  => $this->user,
                    'text'    => Type::string(),
                    'replies' => Type::listOf($this->comment),
                    'parent'  => $this->comment,
                    'content' => $this->content,
                ];
            },
        ]);

        $this->user = new ObjectType([
            'name'       => 'User',
            'interfaces' => [
                $this->node,
            ],
            'fields'     => function () {
                return [
                    'id'   => $this->node->getField('id'),
                    'name' => Type::string(),
                ];
            },
        ]);

        $this->category = new ObjectType([
            'name'       => 'Category',
            'interfaces' => [
                $this->node,
            ],
            'fields'     => function () {
                return [
                    'id'   => $this->node->getField('id'),
                    'name' => Type::string(),
                ];
            },
        ]);

        $this->mention = new UnionType([
            'name'  => 'Mention',
            'types' => [
                $this->user,
                $this->category,
            ],
        ]);

        $this->query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'viewer'        => $this->user,
                'latestContent' => $this->content,
                'node'          => $this->node,
                'mentions'      => Type::listOf($this->mention),
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

        $this->mutation = new ObjectType([
            'name'   => 'Mutation',
            'fields' => [
                'postStory'   => [
                    'type' => $this->postStoryMutation = new ObjectType([
                        'name'   => 'PostStoryMutation',
                        'fields' => [
                            'story' => $this->blogStory,
                        ],
                    ]),
                    'args' => [
                        'input'           => Type::nonNull($this->postStoryMutationInput),
                        'clientRequestId' => Type::string(),
                    ],
                ],
                'postComment' => [
                    'type' => $this->postCommentMutation = new ObjectType([
                        'name'   => 'PostCommentMutation',
                        'fields' => [
                            'comment' => $this->comment,
                        ],
                    ]),
                    'args' => [
                        'input'           => Type::nonNull($this->postCommentMutationInput = new InputObjectType([
                            'name'   => 'PostCommentMutationInput',
                            'fields' => [
                                'text'    => Type::nonNull(Type::string()),
                                'author'  => Type::nonNull(Type::id()),
                                'content' => Type::id(),
                                'parent'  => Type::id(),
                            ],
                        ])),
                        'clientRequestId' => Type::string(),
                    ],
                ],
            ],
        ]);
    }

    public function testEagerTypeResolution() : void
    {
        // Has internal types by default:
        $eagerTypeResolution = new EagerResolution([]);
        $expectedTypeMap     = [
            'ID'      => Type::id(),
            'String'  => Type::string(),
            'Float'   => Type::float(),
            'Int'     => Type::int(),
            'Boolean' => Type::boolean(),
        ];
        self::assertEquals($expectedTypeMap, $eagerTypeResolution->getTypeMap());

        $expectedDescriptor = [
            'version'         => '1.0',
            'typeMap'         => [
                'ID'      => 1,
                'String'  => 1,
                'Float'   => 1,
                'Int'     => 1,
                'Boolean' => 1,
            ],
            'possibleTypeMap' => [],
        ];
        self::assertEquals($expectedDescriptor, $eagerTypeResolution->getDescriptor());

        self::assertNull($eagerTypeResolution->resolveType('User'));
        self::assertSame([], $eagerTypeResolution->resolvePossibleTypes($this->node));
        self::assertSame([], $eagerTypeResolution->resolvePossibleTypes($this->content));
        self::assertSame([], $eagerTypeResolution->resolvePossibleTypes($this->mention));

        $eagerTypeResolution = new EagerResolution([$this->query, $this->mutation]);

        self::assertSame($this->query, $eagerTypeResolution->resolveType('Query'));
        self::assertSame($this->mutation, $eagerTypeResolution->resolveType('Mutation'));
        self::assertSame($this->user, $eagerTypeResolution->resolveType('User'));
        self::assertSame($this->node, $eagerTypeResolution->resolveType('Node'));
        self::assertSame($this->node, $eagerTypeResolution->resolveType('Node'));
        self::assertSame($this->content, $eagerTypeResolution->resolveType('Content'));
        self::assertSame($this->comment, $eagerTypeResolution->resolveType('Comment'));
        self::assertSame($this->mention, $eagerTypeResolution->resolveType('Mention'));
        self::assertSame($this->blogStory, $eagerTypeResolution->resolveType('BlogStory'));
        self::assertSame($this->category, $eagerTypeResolution->resolveType('Category'));
        self::assertSame($this->postStoryMutation, $eagerTypeResolution->resolveType('PostStoryMutation'));
        self::assertSame($this->postStoryMutationInput, $eagerTypeResolution->resolveType('PostStoryMutationInput'));
        self::assertSame($this->postCommentMutation, $eagerTypeResolution->resolveType('PostCommentMutation'));
        self::assertSame(
            $this->postCommentMutationInput,
            $eagerTypeResolution->resolveType('PostCommentMutationInput')
        );

        self::assertEquals([$this->blogStory], $eagerTypeResolution->resolvePossibleTypes($this->content));
        self::assertEquals(
            [$this->user, $this->comment, $this->category, $this->blogStory],
            $eagerTypeResolution->resolvePossibleTypes($this->node)
        );
        self::assertEquals([$this->user, $this->category], $eagerTypeResolution->resolvePossibleTypes($this->mention));

        $expectedTypeMap = [
            'Query'                    => $this->query,
            'Mutation'                 => $this->mutation,
            'User'                     => $this->user,
            'Node'                     => $this->node,
            'String'                   => Type::string(),
            'Content'                  => $this->content,
            'Comment'                  => $this->comment,
            'Mention'                  => $this->mention,
            'BlogStory'                => $this->blogStory,
            'Category'                 => $this->category,
            'PostStoryMutationInput'   => $this->postStoryMutationInput,
            'ID'                       => Type::id(),
            'PostStoryMutation'        => $this->postStoryMutation,
            'PostCommentMutationInput' => $this->postCommentMutationInput,
            'PostCommentMutation'      => $this->postCommentMutation,
            'Float'                    => Type::float(),
            'Int'                      => Type::int(),
            'Boolean'                  => Type::boolean(),
        ];

        self::assertEquals($expectedTypeMap, $eagerTypeResolution->getTypeMap());

        $expectedDescriptor = [
            'version'         => '1.0',
            'typeMap'         => [
                'Query'                    => 1,
                'Mutation'                 => 1,
                'User'                     => 1,
                'Node'                     => 1,
                'String'                   => 1,
                'Content'                  => 1,
                'Comment'                  => 1,
                'Mention'                  => 1,
                'BlogStory'                => 1,
                'Category'                 => 1,
                'PostStoryMutationInput'   => 1,
                'ID'                       => 1,
                'PostStoryMutation'        => 1,
                'PostCommentMutationInput' => 1,
                'PostCommentMutation'      => 1,
                'Float'                    => 1,
                'Int'                      => 1,
                'Boolean'                  => 1,
            ],
            'possibleTypeMap' => [
                'Node'    => [
                    'User'      => 1,
                    'Comment'   => 1,
                    'Category'  => 1,
                    'BlogStory' => 1,
                ],
                'Content' => ['BlogStory' => 1],
                'Mention' => [
                    'User'     => 1,
                    'Category' => 1,
                ],
            ],
        ];

        self::assertEquals($expectedDescriptor, $eagerTypeResolution->getDescriptor());

        // Ignores duplicates and nulls in initialTypes:
        $eagerTypeResolution = new EagerResolution([null, $this->query, null, $this->query, $this->mutation, null]);
        self::assertEquals($expectedTypeMap, $eagerTypeResolution->getTypeMap());
        self::assertEquals($expectedDescriptor, $eagerTypeResolution->getDescriptor());

        // Those types are only part of interface
        self::assertEquals(null, $eagerTypeResolution->resolveType('Link'));
        self::assertEquals(null, $eagerTypeResolution->resolveType('Video'));
        self::assertEquals(null, $eagerTypeResolution->resolveType('VideoMetadata'));

        self::assertEquals([$this->blogStory], $eagerTypeResolution->resolvePossibleTypes($this->content));
        self::assertEquals(
            [$this->user, $this->comment, $this->category, $this->blogStory],
            $eagerTypeResolution->resolvePossibleTypes($this->node)
        );
        self::assertEquals([$this->user, $this->category], $eagerTypeResolution->resolvePossibleTypes($this->mention));

        $eagerTypeResolution = new EagerResolution([null, $this->video, null]);
        self::assertEquals($this->videoMetadata, $eagerTypeResolution->resolveType('VideoMetadata'));
        self::assertEquals($this->video, $eagerTypeResolution->resolveType('Video'));

        self::assertEquals([$this->video], $eagerTypeResolution->resolvePossibleTypes($this->content));
        self::assertEquals(
            [$this->video, $this->user, $this->comment, $this->category],
            $eagerTypeResolution->resolvePossibleTypes($this->node)
        );
        self::assertEquals([], $eagerTypeResolution->resolvePossibleTypes($this->mention));

        $expectedTypeMap = [
            'Video'         => $this->video,
            'Node'          => $this->node,
            'String'        => Type::string(),
            'Content'       => $this->content,
            'User'          => $this->user,
            'Comment'       => $this->comment,
            'Category'      => $this->category,
            'VideoMetadata' => $this->videoMetadata,
            'Float'         => Type::float(),
            'ID'            => Type::id(),
            'Int'           => Type::int(),
            'Boolean'       => Type::boolean(),
        ];
        self::assertEquals($expectedTypeMap, $eagerTypeResolution->getTypeMap());

        $expectedDescriptor = [
            'version'         => '1.0',
            'typeMap'         => [
                'Video'         => 1,
                'Node'          => 1,
                'String'        => 1,
                'Content'       => 1,
                'User'          => 1,
                'Comment'       => 1,
                'Category'      => 1,
                'VideoMetadata' => 1,
                'Float'         => 1,
                'ID'            => 1,
                'Int'           => 1,
                'Boolean'       => 1,
            ],
            'possibleTypeMap' => [
                'Node'    => [
                    'Video'    => 1,
                    'User'     => 1,
                    'Comment'  => 1,
                    'Category' => 1,
                ],
                'Content' => ['Video' => 1],
            ],
        ];
        self::assertEquals($expectedDescriptor, $eagerTypeResolution->getDescriptor());
    }

    public function testLazyResolutionFollowsEagerResolution() : void
    {
        // Lazy resolution should work the same way as eager resolution works, except that it should load types on demand
        $eager           = new EagerResolution([]);
        $emptyDescriptor = $eager->getDescriptor();

        $typeLoader = static function () {
            throw new Exception('This should be never called for empty descriptor');
        };

        $lazy = new LazyResolution($emptyDescriptor, $typeLoader);
        self::assertSame($eager->resolveType('User'), $lazy->resolveType('User'));
        self::assertSame($eager->resolvePossibleTypes($this->node), $lazy->resolvePossibleTypes($this->node));
        self::assertSame($eager->resolvePossibleTypes($this->content), $lazy->resolvePossibleTypes($this->content));
        self::assertSame($eager->resolvePossibleTypes($this->mention), $lazy->resolvePossibleTypes($this->mention));

        $eager = new EagerResolution([$this->query, $this->mutation]);

        $called     = 0;
        $descriptor = $eager->getDescriptor();
        $typeLoader = function ($name) use (&$called) {
            $called++;
            $prop = lcfirst($name);

            return $this->{$prop};
        };

        $lazy = new LazyResolution($descriptor, $typeLoader);

        self::assertSame($eager->resolveType('Query'), $lazy->resolveType('Query'));
        self::assertSame(1, $called);
        self::assertSame($eager->resolveType('Mutation'), $lazy->resolveType('Mutation'));
        self::assertSame(2, $called);
        self::assertSame($eager->resolveType('User'), $lazy->resolveType('User'));
        self::assertSame(3, $called);
        self::assertSame($eager->resolveType('User'), $lazy->resolveType('User'));
        self::assertSame(3, $called);
        self::assertSame($eager->resolveType('Node'), $lazy->resolveType('Node'));
        self::assertSame($eager->resolveType('Node'), $lazy->resolveType('Node'));
        self::assertSame(4, $called);
        self::assertSame($eager->resolveType('Content'), $lazy->resolveType('Content'));
        self::assertSame($eager->resolveType('Comment'), $lazy->resolveType('Comment'));
        self::assertSame($eager->resolveType('Mention'), $lazy->resolveType('Mention'));
        self::assertSame($eager->resolveType('BlogStory'), $lazy->resolveType('BlogStory'));
        self::assertSame($eager->resolveType('Category'), $lazy->resolveType('Category'));
        self::assertSame($eager->resolveType('PostStoryMutation'), $lazy->resolveType('PostStoryMutation'));
        self::assertSame($eager->resolveType('PostStoryMutationInput'), $lazy->resolveType('PostStoryMutationInput'));
        self::assertSame($eager->resolveType('PostCommentMutation'), $lazy->resolveType('PostCommentMutation'));
        self::assertSame(
            $eager->resolveType('PostCommentMutationInput'),
            $lazy->resolveType('PostCommentMutationInput')
        );
        self::assertSame(13, $called);

        self::assertEquals($eager->resolvePossibleTypes($this->content), $lazy->resolvePossibleTypes($this->content));
        self::assertEquals($eager->resolvePossibleTypes($this->node), $lazy->resolvePossibleTypes($this->node));
        self::assertEquals($eager->resolvePossibleTypes($this->mention), $lazy->resolvePossibleTypes($this->mention));

        $called = 0;
        $eager  = new EagerResolution([$this->video]);
        $lazy   = new LazyResolution($eager->getDescriptor(), $typeLoader);

        self::assertEquals($eager->resolveType('VideoMetadata'), $lazy->resolveType('VideoMetadata'));
        self::assertEquals($eager->resolveType('Video'), $lazy->resolveType('Video'));
        self::assertEquals(2, $called);

        self::assertEquals($eager->resolvePossibleTypes($this->content), $lazy->resolvePossibleTypes($this->content));
        self::assertEquals($eager->resolvePossibleTypes($this->node), $lazy->resolvePossibleTypes($this->node));
        self::assertEquals($eager->resolvePossibleTypes($this->mention), $lazy->resolvePossibleTypes($this->mention));
    }

    public function testLazyThrowsOnInvalidLoadedType() : void
    {
        $lazy = $this->createLazy();
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Lazy Type Resolution Error: Expecting GraphQL Type instance, but got integer');
        $lazy->resolveType('int');
    }

    private function createLazy()
    {
        $descriptor = [
            'version'         => '1.0',
            'typeMap'         => [
                'null' => 1,
                'int'  => 1,
            ],
            'possibleTypeMap' => [
                'a' => ['null' => 1],
                'b' => ['int' => 1],
            ],
        ];

        $invalidTypeLoader = static function ($name) {
            switch ($name) {
                case 'null':
                    return null;
                case 'int':
                    return 7;
            }
        };

        $lazy  = new LazyResolution($descriptor, $invalidTypeLoader);
        $value = $lazy->resolveType('null');
        self::assertEquals(null, $value);

        return $lazy;
    }

    public function testLazyThrowsOnInvalidLoadedPossibleType() : void
    {
        $tmp  = new InterfaceType(['name' => 'a', 'fields' => []]);
        $lazy = $this->createLazy();
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Lazy Type Resolution Error: Implementation null of interface a is expected to be instance of ObjectType, but got NULL');
        $lazy->resolvePossibleTypes($tmp);
    }

    public function testLazyThrowsOnInvalidLoadedPossibleTypeWithInteger() : void
    {
        $tmp  = new InterfaceType(['name' => 'b', 'fields' => []]);
        $lazy = $this->createLazy();
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Lazy Type Resolution Error: Expecting GraphQL Type instance, but got integer');
        $lazy->resolvePossibleTypes($tmp);
    }
}
