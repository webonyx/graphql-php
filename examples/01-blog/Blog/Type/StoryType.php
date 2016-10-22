<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\TypeSystem;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class StoryType
 * @package GraphQL\Examples\Social\Type
 */
class StoryType extends BaseType
{
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';
    const LIKE = 'LIKE';
    const UNLIKE = 'UNLIKE';
    const REPLY = 'REPLY';

    public function __construct(TypeSystem $types)
    {
        $this->definition = new ObjectType([
            'name' => 'Story',
            'fields' => function() use ($types) {
                return [
                    'id' => $types->id(),
                    'author' => $types->user(),
                    'mentions' => $types->listOf($types->mention()),
                    'totalCommentCount' => $types->int(),
                    'comments' => [
                        'type' => $types->listOf($types->comment()),
                        'args' => [
                            'after' => [
                                'type' => $types->id(),
                                'description' => 'Load all comments listed after given comment ID'
                            ],
                            'limit' => [
                                'type' => $types->int(),
                                'defaultValue' => 5
                            ]
                        ]
                    ],
                    'affordances' => $types->listOf(new EnumType([
                        'name' => 'StoryAffordancesEnum',
                        'values' => [
                            self::EDIT,
                            self::DELETE,
                            self::LIKE,
                            self::UNLIKE,
                            self::REPLY
                        ]
                    ])),
                    'hasViewerLiked' => $types->boolean(),

                    $types->htmlField('body'),
                ];
            },
            'interfaces' => [
                $types->node()
            ],
            'resolveField' => function($value, $args, $context, ResolveInfo $info) {
                if (method_exists($this, $info->fieldName)) {
                    return $this->{$info->fieldName}($value, $args, $context, $info);
                } else {
                    return $value->{$info->fieldName};
                }
            }
        ]);
    }

    public function author(Story $story, $args, AppContext $context)
    {
        return $context->dataSource->findUser($story->authorId);
    }

    public function affordances(Story $story, $args, AppContext $context)
    {
        $isViewer = $context->viewer === $context->dataSource->findUser($story->authorId);
        $isLiked = $context->dataSource->isLikedBy($story->id, $context->viewer->id);

        if ($isViewer) {
            $affordances[] = self::EDIT;
            $affordances[] = self::DELETE;
        }
        if ($isLiked) {
            $affordances[] = self::UNLIKE;
        } else {
            $affordances[] = self::LIKE;
        }
        return $affordances;
    }

    public function hasViewerLiked(Story $story, $args, AppContext $context)
    {
        return $context->dataSource->isLikedBy($story->id, $context->viewer->id);
    }

    public function totalCommentCount(Story $story, $args, AppContext $context)
    {
        return $context->dataSource->countComments($story->id);
    }

    public function comments(Story $story, $args, AppContext $context)
    {
        $args += ['after' => null];
        return $context->dataSource->findComments($story->id, $args['limit'], $args['after']);
    }
}
