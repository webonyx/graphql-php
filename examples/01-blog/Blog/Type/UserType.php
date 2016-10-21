<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\TypeSystem;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

class UserType
{
    public static function getDefinition(TypeSystem $types)
    {
        $handler = new self();

        return new ObjectType([
            'name' => 'User',
            'fields' => function() use ($types) {
                return [
                    'id' => $types->id(),
                    'email' => $types->email(),
                    'photo' => [
                        'type' => $types->image(),
                        'description' => 'User photo URL',
                        'args' => [
                            'size' => $types->nonNull($types->imageSizeEnum()),
                        ]
                    ],
                    'firstName' => [
                        'type' => $types->string(),
                    ],
                    'lastName' => [
                        'type' => $types->string(),
                    ],
                    'lastStoryPosted' => $types->story(),
                    'error' => [
                        'type' => $types->string(),
                        'resolve' => function() {
                            throw new \Exception("This is error field");
                        }
                    ]
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
            }
        ]);
    }

    public function photo(User $user, $args, AppContext $context)
    {
        return $context->dataSource->getUserPhoto($user, $args['size']);
    }

    public function lastStoryPosted(User $user, $args, AppContext $context)
    {
        return $context->dataSource->findLastStoryFor($user->id);
    }
}
