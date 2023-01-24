<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;

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
                        'size' => new NonNull(Types::imageSize()),
                    ],
                    'resolve' => static fn (User $user, array $args): Image => DataSource::getUserPhoto($user->id, $args['size']),
                ],
                'firstName' => Types::string(),
                'lastName' => Types::string(),
                'lastStoryPosted' => [
                    'type' => Types::story(),
                    'resolve' => static fn (User $user): ?Story => DataSource::findLastStoryFor($user->id),
                ],
                'fieldWithError' => [
                    'type' => Types::string(),
                    'resolve' => static function (): void {
                        throw new \Exception('This is error field');
                    },
                ],
            ],
            'interfaces' => [Types::node()],
        ]);
    }
}
