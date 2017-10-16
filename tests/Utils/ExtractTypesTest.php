<?php
namespace Utils;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\TypeInfo;

class ExtractTypesTest extends \PHPUnit_Framework_TestCase
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

    public function testExtractTypesFromQuery()
    {
        $expectedTypeMap = [
            'Query' => $this->query,
            'User' => $this->user,
            'Node' => $this->node,
            'String' => Type::string(),
            'Content' => $this->content,
            'Comment' => $this->comment,
            'Mention' => $this->mention,
            'Category' => $this->category,
        ];

        $actualTypeMap = TypeInfo::extractTypes($this->query);
        $this->assertEquals($expectedTypeMap, $actualTypeMap);
    }

    public function testExtractTypesFromMutation()
    {
        $expectedTypeMap = [
            'Mutation' => $this->mutation,
            'User' => $this->user,
            'Node' => $this->node,
            'String' => Type::string(),
            'Content' => $this->content,
            'Comment' => $this->comment,
            'BlogStory' => $this->blogStory,
            'Category' => $this->category,
            'PostStoryMutationInput' => $this->postStoryMutationInput,
            'ID' => Type::id(),
            'PostStoryMutation' => $this->postStoryMutation,
            'PostCommentMutationInput' => $this->postCommentMutationInput,
            'PostCommentMutation' => $this->postCommentMutation,
        ];

        $actualTypeMap = TypeInfo::extractTypes($this->mutation);
        $this->assertEquals($expectedTypeMap, $actualTypeMap);
    }

    public function testThrowsOnMultipleTypesWithSameName()
    {
        $otherUserType = new ObjectType([
            'name' => 'User',
            'fields' => ['a' => Type::string()]
        ]);

        $queryType = new ObjectType([
            'name' => 'Test',
            'fields' => [
                'otherUser' => $otherUserType,
                'user' => $this->user
            ]
        ]);

        $this->setExpectedException(
            '\GraphQL\Error\InvariantViolation',
            "Schema must contain unique named types but contains multiple types named \"User\""
        );
        TypeInfo::extractTypes($queryType);
    }
}
