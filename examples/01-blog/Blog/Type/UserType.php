<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use Exception;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

use function method_exists;
use function ucfirst;

class UserType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'name' => 'User',
            'description' => 'Our blog authors',
            'fields' => static function () {
                return [
                    'id' => Types::id(),
                    'email' => Types::email(),
                    'photo' => [
                        'type' => Types::image(),
                        'description' => 'User photo URL',
                        'args' => [
                            'size' => Types::nonNull(Types::imageSizeEnum()),
                        ],
                    ],
                    'firstName' => [
                        'type' => Types::string(),
                    ],
                    'lastName' => [
                        'type' => Types::string(),
                    ],
                    'lastStoryPosted' => Types::story(),
                    'fieldWithError' => [
                        'type' => Types::string(),
                        'resolve' => static function () {
                            throw new Exception('This is error field');
                        },
                    ],
                ];
            },
            'interfaces' => [Types::node()],
            'resolveField' => function ($user, $args, $context, ResolveInfo $info) {
                $method = 'resolve' . ucfirst($info->fieldName);
                if (method_exists($this, $method)) {
                    return $this->{$method}($user, $args, $context, $info);
                }

                return $user->{$info->fieldName};
            },
        ];
        parent::__construct($config);
    }

    public function resolvePhoto(User $user, $args)
    {
        return DataSource::getUserPhoto($user->id, $args['size']);
    }

    public function resolveLastStoryPosted(User $user)
    {
        return DataSource::findLastStoryFor($user->id);
    }
}
