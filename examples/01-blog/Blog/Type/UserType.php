<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\TypeSystem;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

class UserType extends BaseType
{
    public function __construct(TypeSystem $types)
    {
        // Option #1: using composition over inheritance to define type, see ImageType for inheritance example
        $this->definition = new ObjectType([
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
                    'fieldWithError' => [
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
            'resolveField' => function($value, $args, $context, ResolveInfo $info) {
                if (method_exists($this, $info->fieldName)) {
                    return $this->{$info->fieldName}($value, $args, $context, $info);
                } else {
                    return $value->{$info->fieldName};
                }
            }
        ]);
    }

    public function photo(User $user, $args, AppContext $context)
    {
        return $context->dataSource->getUserPhoto($user->id, $args['size']);
    }

    public function lastStoryPosted(User $user, $args, AppContext $context)
    {
        return $context->dataSource->findLastStoryFor($user->id);
    }
}
