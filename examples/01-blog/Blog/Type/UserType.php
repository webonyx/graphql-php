<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use Exception;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\Image;
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
        parent::__construct([
            'name' => 'User',
            'description' => 'Our blog authors',
            'fields' => static fn (): array => [
                'id' => Types::id(),
                'email' => Types::email(),
                'photo' => [
                    'type' => Types::image(),
                    'description' => 'User photo URL',
                    'args' => [
                        'size' => Types::nonNull(Types::imageSize()),
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
                    'resolve' => static function (): void {
                        throw new Exception('This is error field');
                    },
                ],
            ],
            'interfaces' => [Types::node()],
            'resolveField' => function ($user, $args, $context, ResolveInfo $info) {
                $fieldName = $info->fieldName;

                $method = 'resolve' . ucfirst($fieldName);
                if (method_exists($this, $method)) {
                    return $this->{$method}($user, $args, $context, $info);
                }

                return $user->{$fieldName};
            },
        ]);
    }

    /**
     * @param array{size: string} $args
     */
    public function resolvePhoto(User $user, array $args): Image
    {
        return DataSource::getUserPhoto($user->id, $args['size']);
    }

    public function resolveLastStoryPosted(User $user)
    {
        return DataSource::findLastStoryFor($user->id);
    }
}
