<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\TypeSystem;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

class QueryType
{
    public static function getDefinition(TypeSystem $types)
    {
        $handler = new self();

        return new ObjectType([
            'name' => 'Query',
            'fields' => [
                'user' => [
                    'type' => $types->user(),
                    'args' => [
                        'id' => [
                            'type' => $types->id(),
                            'defaultValue' => 1
                        ]
                    ]
                ],
                'viewer' => $types->user(),
                'lastStoryPosted' => $types->story(),
                'stories' => [
                    'type' => $types->listOf($types->story()),
                    'args' => []
                ]
            ],
            'resolveField' => function($val, $args, $context, ResolveInfo $info) use ($handler) {
                return $handler->{$info->fieldName}($val, $args, $context, $info);
            }
        ]);
    }

    public function user($val, $args, AppContext $context)
    {
        return $context->dataSource->findUser($args['id']);
    }

    public function viewer($val, $args, AppContext $context)
    {
        return $context->viewer;
    }

    public function lastStoryPosted($val, $args, AppContext $context)
    {
        return $context->dataSource->findLatestStory();
    }
}
