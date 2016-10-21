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
class StoryType
{
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';
    const LIKE = 'LIKE';
    const UNLIKE = 'UNLIKE';

    const FORMAT_TEXT = 'TEXT';
    const FORMAT_HTML = 'HTML';

    /**
     * @param TypeSystem $types
     * @return ObjectType
     */
    public static function getDefinition(TypeSystem $types)
    {
        // Type instance containing resolvers for field definitions
        $handler = new self();

        // Return definition for this type:
        return new ObjectType([
            'name' => 'Story',
            'fields' => function() use ($types) {
                return [
                    'id' => $types->id(),
                    'author' => $types->user(),
                    'body' => [
                        'type' => $types->string(),
                        'args' => [
                            'format' => new EnumType([
                                'name' => 'StoryFormatEnum',
                                'values' => [self::FORMAT_TEXT, self::FORMAT_HTML]
                            ]),
                            'maxLength' => $types->int()
                        ]
                    ],
                    'isLiked' => $types->boolean(),
                    'affordances' => $types->listOf(new EnumType([
                        'name' => 'StoryAffordancesEnum',
                        'values' => [
                            self::EDIT,
                            self::DELETE,
                            self::LIKE,
                            self::UNLIKE
                        ]
                    ]))
                ];
            },
            'interfaces' => [
                $types->node()
            ],
            'resolveField' => function($value, $args, $context, ResolveInfo $info) use ($handler) {
                if (method_exists($handler, $info->fieldName)) {
                    return $handler->{$info->fieldName}($value, $args, $context, $info);
                } else {
                    return $value->{$info->fieldName};
                }
            },
            'containerType' => $handler
        ]);
    }

    /**
     * @param Story $story
     * @param $args
     * @param AppContext $context
     * @return User|null
     */
    public function author(Story $story, $args, AppContext $context)
    {
        if ($story->isAnonymous) {
            return null;
        }
        return $context->dataSource->findUser($story->authorId);
    }

    /**
     * @param Story $story
     * @param $args
     * @param AppContext $context
     * @return array
     */
    public function affordances(Story $story, $args, AppContext $context)
    {
        $isViewer = $context->viewer === $context->dataSource->findUser($story->authorId);
        $isLiked = $context->dataSource->isLikedBy($story, $context->viewer);

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
}
