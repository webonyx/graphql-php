<?php
namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\EagerResolution;
use GraphQL\Type\LazyResolution;

class ResolutionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ObjectType
     */
    private $query;

    /**
     * @var ObjectType
     */
    private $mutation;

    /**
     * @var InterfaceType
     */
    private $node;

    /**
     * @var InterfaceType
     */
    private $content;

    /**
     * @var ObjectType
     */
    private $blogStory;

    /**
     * @var ObjectType
     */
    private $link;

    /**
     * @var ObjectType
     */
    private $video;

    /**
     * @var ObjectType
     */
    private $videoMetadata;

    /**
     * @var ObjectType
     */
    private $comment;

    /**
     * @var ObjectType
     */
    private $user;

    /**
     * @var ObjectType
     */
    private $category;

    /**
     * @var UnionType
     */
    private $mention;

    private $postStoryMutation;

    private $postStoryMutationInput;

    private $postCommentMutation;

    private $postCommentMutationInput;

    public function setUp()
    {
        $this->node = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'id' => Type::string()
            ]
        ]);

        $this->content = new InterfaceType([
            'name' => 'Content',
            'fields' => function() {
                return [
                    'title' => Type::string(),
                    'body' => Type::string(),
                    'author' => $this->user,
                    'comments' => Type::listOf($this->comment),
                    'categories' => Type::listOf($this->category)
                ];
            }
        ]);

        $this->blogStory = new ObjectType([
            'name' => 'BlogStory',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                    $this->content->getField('author'),
                    $this->content->getField('comments'),
                    $this->content->getField('categories')
                ];
            },
        ]);

        $this->link = new ObjectType([
            'name' => 'Link',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                    $this->content->getField('author'),
                    $this->content->getField('comments'),
                    $this->content->getField('categories'),
                    'url' => Type::string()
                ];
            },
        ]);

        $this->video = new ObjectType([
            'name' => 'Video',
            'interfaces' => [
                $this->node,
                $this->content
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    $this->content->getField('title'),
                    $this->content->getField('body'),
                    $this->content->getField('author'),
                    $this->content->getField('comments'),
                    $this->content->getField('categories'),
                    'streamUrl' => Type::string(),
                    'downloadUrl' => Type::string(),
                    'metadata' => $this->videoMetadata = new ObjectType([
                        'name' => 'VideoMetadata',
                        'fields' => [
                            'lat' => Type::float(),
                            'lng' => Type::float()
                        ]
                    ])
                ];
            }
        ]);

        $this->comment = new ObjectType([
            'name' => 'Comment',
            'interfaces' => [
                $this->node
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    'author' => $this->user,
                    'text' => Type::string(),
                    'replies' => Type::listOf($this->comment),
                    'parent' => $this->comment,
                    'content' => $this->content
                ];
            }
        ]);

        $this->user = new ObjectType([
            'name' => 'User',
            'interfaces' => [
                $this->node
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    'name' => Type::string(),
                ];
            }
        ]);

        $this->category = new ObjectType([
            'name' => 'Category',
            'interfaces' => [
                $this->node
            ],
            'fields' => function() {
                return [
                    $this->node->getField('id'),
                    'name' => Type::string()
                ];
            }
        ]);

        $this->mention = new UnionType([
            'name' => 'Mention',
            'types' => [
                $this->user,
                $this->category
            ]
        ]);

        $this->query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'viewer' => $this->user,
                'latestContent' => $this->content,
                'node' => $this->node,
                'mentions' => Type::listOf($this->mention)
            ]
        ]);

        $this->mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'postStory' => [
                    'type' => $this->postStoryMutation = new ObjectType([
                        'name' => 'PostStoryMutation',
                        'fields' => [
                            'story' => $this->blogStory
                        ]
                    ]),
                    'args' => [
                        'input' => Type::nonNull($this->postStoryMutationInput = new InputObjectType([
                            'name' => 'PostStoryMutationInput',
                            'fields' => [
                                'title' => Type::string(),
                                'body' => Type::string(),
                                'author' => Type::id(),
                                'category' => Type::id()
                            ]
                        ])),
                        'clientRequestId' => Type::string()
                    ]
                ],
                'postComment' => [
                    'type' => $this->postCommentMutation = new ObjectType([
                        'name' => 'PostCommentMutation',
                        'fields' => [
                            'comment' => $this->comment
                        ]
                    ]),
                    'args' => [
                        'input' => Type::nonNull($this->postCommentMutationInput = new InputObjectType([
                            'name' => 'PostCommentMutationInput',
                            'fields' => [
                                'text' => Type::nonNull(Type::string()),
                                'author' => Type::nonNull(Type::id()),
                                'content' => Type::id(),
                                'parent' => Type::id()
                            ]
                        ])),
                        'clientRequestId' => Type::string()
                    ]
                ]
            ]
        ]);
    }

    public function testEagerTypeResolution()
    {
        // Has internal types by default:
        $eagerTypeResolution = new EagerResolution([]);
        $expectedTypeMap = [
            'ID' => Type::id(),
            'String' => Type::string(),
            'Float' => Type::float(),
            'Int' => Type::int(),
            'Boolean' => Type::boolean()
        ];
        $this->assertEquals($expectedTypeMap, $eagerTypeResolution->getTypeMap());

        $expectedDescriptor = [
            'version' => '1.0',
            'typeMap' => [
                'ID' => 1,
                'String' => 1,
                'Float' => 1,
                'Int' => 1,
                'Boolean' => 1,
            ],
            'possibleTypeMap' => []
        ];
        $this->assertEquals($expectedDescriptor, $eagerTypeResolution->getDescriptor());

        $this->assertSame(null, $eagerTypeResolution->resolveType('User'));
        $this->assertSame([], $eagerTypeResolution->resolvePossibleTypes($this->node));
        $this->assertSame([], $eagerTypeResolution->resolvePossibleTypes($this->content));
        $this->assertSame([], $eagerTypeResolution->resolvePossibleTypes($this->mention));

        $eagerTypeResolution = new EagerResolution([$this->query, $this->mutation]);

        $this->assertSame($this->query, $eagerTypeResolution->resolveType('Query'));
        $this->assertSame($this->mutation, $eagerTypeResolution->resolveType('Mutation'));
        $this->assertSame($this->user, $eagerTypeResolution->resolveType('User'));
        $this->assertSame($this->node, $eagerTypeResolution->resolveType('Node'));
        $this->assertSame($this->node, $eagerTypeResolution->resolveType('Node'));
        $this->assertSame($this->content, $eagerTypeResolution->resolveType('Content'));
        $this->assertSame($this->comment, $eagerTypeResolution->resolveType('Comment'));
        $this->assertSame($this->mention, $eagerTypeResolution->resolveType('Mention'));
        $this->assertSame($this->blogStory, $eagerTypeResolution->resolveType('BlogStory'));
        $this->assertSame($this->category, $eagerTypeResolution->resolveType('Category'));
        $this->assertSame($this->postStoryMutation, $eagerTypeResolution->resolveType('PostStoryMutation'));
        $this->assertSame($this->postStoryMutationInput, $eagerTypeResolution->resolveType('PostStoryMutationInput'));
        $this->assertSame($this->postCommentMutation, $eagerTypeResolution->resolveType('PostCommentMutation'));
        $this->assertSame($this->postCommentMutationInput, $eagerTypeResolution->resolveType('PostCommentMutationInput'));

        $this->assertEquals([$this->blogStory], $eagerTypeResolution->resolvePossibleTypes($this->content));
        $this->assertEquals([$this->user, $this->comment, $this->category, $this->blogStory], $eagerTypeResolution->resolvePossibleTypes($this->node));
        $this->assertEquals([$this->user, $this->category], $eagerTypeResolution->resolvePossibleTypes($this->mention));

        $expectedTypeMap = [
            'Query' => $this->query,
            'Mutation' => $this->mutation,
            'User' => $this->user,
            'Node' => $this->node,
            'String' => Type::string(),
            'Content' => $this->content,
            'Comment' => $this->comment,
            'Mention' => $this->mention,
            'BlogStory' => $this->blogStory,
            'Category' => $this->category,
            'PostStoryMutationInput' => $this->postStoryMutationInput,
            'ID' => Type::id(),
            'PostStoryMutation' => $this->postStoryMutation,
            'PostCommentMutationInput' => $this->postCommentMutationInput,
            'PostCommentMutation' => $this->postCommentMutation,
            'Float' => Type::float(),
            'Int' => Type::int(),
            'Boolean' => Type::boolean()
        ];

        $this->assertEquals($expectedTypeMap, $eagerTypeResolution->getTypeMap());

        $expectedDescriptor = [
            'version' => '1.0',
            'typeMap' => [
                'Query' => 1,
                'Mutation' => 1,
                'User' => 1,
                'Node' => 1,
                'String' => 1,
                'Content' => 1,
                'Comment' => 1,
                'Mention' => 1,
                'BlogStory' => 1,
                'Category' => 1,
                'PostStoryMutationInput' => 1,
                'ID' => 1,
                'PostStoryMutation' => 1,
                'PostCommentMutationInput' => 1,
                'PostCommentMutation' => 1,
                'Float' => 1,
                'Int' => 1,
                'Boolean' => 1
            ],
            'possibleTypeMap' => [
                'Node' => [
                    'User' => 1,
                    'Comment' => 1,
                    'Category' => 1,
                    'BlogStory' => 1
                ],
                'Content' => [
                    'BlogStory' => 1
                ],
                'Mention' => [
                    'User' => 1,
                    'Category' => 1
                ]
            ]
        ];

        $this->assertEquals($expectedDescriptor, $eagerTypeResolution->getDescriptor());

        // Ignores duplicates and nulls in initialTypes:
        $eagerTypeResolution = new EagerResolution([null, $this->query, null, $this->query, $this->mutation, null]);
        $this->assertEquals($expectedTypeMap, $eagerTypeResolution->getTypeMap());
        $this->assertEquals($expectedDescriptor, $eagerTypeResolution->getDescriptor());

        // Those types are only part of interface
        $this->assertEquals(null, $eagerTypeResolution->resolveType('Link'));
        $this->assertEquals(null, $eagerTypeResolution->resolveType('Video'));
        $this->assertEquals(null, $eagerTypeResolution->resolveType('VideoMetadata'));

        $this->assertEquals([$this->blogStory], $eagerTypeResolution->resolvePossibleTypes($this->content));
        $this->assertEquals([$this->user, $this->comment, $this->category, $this->blogStory], $eagerTypeResolution->resolvePossibleTypes($this->node));
        $this->assertEquals([$this->user, $this->category], $eagerTypeResolution->resolvePossibleTypes($this->mention));

        $eagerTypeResolution = new EagerResolution([null, $this->video, null]);
        $this->assertEquals($this->videoMetadata, $eagerTypeResolution->resolveType('VideoMetadata'));
        $this->assertEquals($this->video, $eagerTypeResolution->resolveType('Video'));

        $this->assertEquals([$this->video], $eagerTypeResolution->resolvePossibleTypes($this->content));
        $this->assertEquals([$this->video, $this->user, $this->comment, $this->category], $eagerTypeResolution->resolvePossibleTypes($this->node));
        $this->assertEquals([], $eagerTypeResolution->resolvePossibleTypes($this->mention));

        $expectedTypeMap = [
            'Video' => $this->video,
            'Node' => $this->node,
            'String' => Type::string(),
            'Content' => $this->content,
            'User' => $this->user,
            'Comment' => $this->comment,
            'Category' => $this->category,
            'VideoMetadata' => $this->videoMetadata,
            'Float' => Type::float(),
            'ID' => Type::id(),
            'Int' => Type::int(),
            'Boolean' => Type::boolean()
        ];
        $this->assertEquals($expectedTypeMap, $eagerTypeResolution->getTypeMap());

        $expectedDescriptor = [
            'version' => '1.0',
            'typeMap' => [
                'Video' => 1,
                'Node' => 1,
                'String' => 1,
                'Content' => 1,
                'User' => 1,
                'Comment' => 1,
                'Category' => 1,
                'VideoMetadata' => 1,
                'Float' => 1,
                'ID' => 1,
                'Int' => 1,
                'Boolean' => 1
            ],
            'possibleTypeMap' => [
                'Node' => [
                    'Video' => 1,
                    'User' => 1,
                    'Comment' => 1,
                    'Category' => 1
                ],
                'Content' => [
                    'Video' => 1
                ]
            ]
        ];
        $this->assertEquals($expectedDescriptor, $eagerTypeResolution->getDescriptor());
    }

    public function testLazyResolutionFollowsEagerResolution()
    {
        // Lazy resolution should work the same way as eager resolution works, except that it should load types on demand
        $eager = new EagerResolution([]);
        $emptyDescriptor = $eager->getDescriptor();

        $typeLoader = function($name) {
            throw new \Exception("This should be never called for empty descriptor");
        };

        $lazy = new LazyResolution($emptyDescriptor, $typeLoader);
        $this->assertSame($eager->resolveType('User'), $lazy->resolveType('User'));
        $this->assertSame($eager->resolvePossibleTypes($this->node), $lazy->resolvePossibleTypes($this->node));
        $this->assertSame($eager->resolvePossibleTypes($this->content), $lazy->resolvePossibleTypes($this->content));
        $this->assertSame($eager->resolvePossibleTypes($this->mention), $lazy->resolvePossibleTypes($this->mention));

        $eager = new EagerResolution([$this->query, $this->mutation]);

        $called = 0;
        $descriptor = $eager->getDescriptor();
        $typeLoader = function($name) use (&$called) {
            $called++;
            $prop = lcfirst($name);
            return $this->{$prop};
        };

        $lazy = new LazyResolution($descriptor, $typeLoader);

        $this->assertSame($eager->resolveType('Query'), $lazy->resolveType('Query'));
        $this->assertSame(1, $called);
        $this->assertSame($eager->resolveType('Mutation'), $lazy->resolveType('Mutation'));
        $this->assertSame(2, $called);
        $this->assertSame($eager->resolveType('User'), $lazy->resolveType('User'));
        $this->assertSame(3, $called);
        $this->assertSame($eager->resolveType('User'), $lazy->resolveType('User'));
        $this->assertSame(3, $called);
        $this->assertSame($eager->resolveType('Node'), $lazy->resolveType('Node'));
        $this->assertSame($eager->resolveType('Node'), $lazy->resolveType('Node'));
        $this->assertSame(4, $called);
        $this->assertSame($eager->resolveType('Content'), $lazy->resolveType('Content'));
        $this->assertSame($eager->resolveType('Comment'), $lazy->resolveType('Comment'));
        $this->assertSame($eager->resolveType('Mention'), $lazy->resolveType('Mention'));
        $this->assertSame($eager->resolveType('BlogStory'), $lazy->resolveType('BlogStory'));
        $this->assertSame($eager->resolveType('Category'), $lazy->resolveType('Category'));
        $this->assertSame($eager->resolveType('PostStoryMutation'), $lazy->resolveType('PostStoryMutation'));
        $this->assertSame($eager->resolveType('PostStoryMutationInput'), $lazy->resolveType('PostStoryMutationInput'));
        $this->assertSame($eager->resolveType('PostCommentMutation'), $lazy->resolveType('PostCommentMutation'));
        $this->assertSame($eager->resolveType('PostCommentMutationInput'), $lazy->resolveType('PostCommentMutationInput'));
        $this->assertSame(13, $called);

        $this->assertEquals($eager->resolvePossibleTypes($this->content), $lazy->resolvePossibleTypes($this->content));
        $this->assertEquals($eager->resolvePossibleTypes($this->node), $lazy->resolvePossibleTypes($this->node));
        $this->assertEquals($eager->resolvePossibleTypes($this->mention), $lazy->resolvePossibleTypes($this->mention));

        $called = 0;
        $eager = new EagerResolution([$this->video]);
        $lazy = new LazyResolution($eager->getDescriptor(), $typeLoader);

        $this->assertEquals($eager->resolveType('VideoMetadata'), $lazy->resolveType('VideoMetadata'));
        $this->assertEquals($eager->resolveType('Video'), $lazy->resolveType('Video'));
        $this->assertEquals(2, $called);

        $this->assertEquals($eager->resolvePossibleTypes($this->content), $lazy->resolvePossibleTypes($this->content));
        $this->assertEquals($eager->resolvePossibleTypes($this->node), $lazy->resolvePossibleTypes($this->node));
        $this->assertEquals($eager->resolvePossibleTypes($this->mention), $lazy->resolvePossibleTypes($this->mention));
    }

    public function testLazyThrowsOnInvalidLoadedType()
    {
        $descriptor = [
            'version' => '1.0',
            'typeMap' => [
                'null' => 1,
                'int' => 1
            ],
            'possibleTypeMap' => [
                'a' => [
                    'null' => 1,
                ],
                'b' => [
                    'int' => 1
                ]
            ]
        ];

        $invalidTypeLoader = function($name) {
            switch ($name) {
                case 'null':
                    return null;
                case 'int':
                    return 7;
            }
        };

        $lazy = new LazyResolution($descriptor, $invalidTypeLoader);
        $value = $lazy->resolveType('null');
        $this->assertEquals(null, $value);

        try {
            $lazy->resolveType('int');
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                "Lazy Type Resolution Error: Expecting GraphQL Type instance, but got integer",
                $e->getMessage()
            );

        }

        try {
            $tmp = new InterfaceType(['name' => 'a', 'fields' => []]);
            $lazy->resolvePossibleTypes($tmp);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Lazy Type Resolution Error: Implementation null of interface a is expected to be instance of ObjectType, but got NULL',
                $e->getMessage()
            );
        }

        try {
            $tmp = new InterfaceType(['name' => 'b', 'fields' => []]);
            $lazy->resolvePossibleTypes($tmp);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Lazy Type Resolution Error: Expecting GraphQL Type instance, but got integer',
                $e->getMessage()
            );
        }
    }
}
