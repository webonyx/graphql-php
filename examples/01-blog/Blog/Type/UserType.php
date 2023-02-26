<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\Image;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Type\Enum\ImageSizeType;
use GraphQL\Examples\Blog\Type\Scalar\EmailType;
use GraphQL\Examples\Blog\TypeRegistry;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class UserType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'User',
            'description' => 'Our blog authors',
            'fields' => static fn (): array => [
                'id' => Type::id(),
                'email' => TypeRegistry::type(EmailType::class),
                'photo' => [
                    'type' => TypeRegistry::type(ImageType::class),
                    'description' => 'User photo URL',
                    'args' => [
                        'size' => new NonNull(TypeRegistry::type(ImageSizeType::class)),
                    ],
                    'resolve' => static fn (User $user, array $args): Image => DataSource::getUserPhoto($user->id, $args['size']),
                ],
                'firstName' => Type::string(),
                'lastName' => Type::string(),
                'lastStoryPosted' => [
                    'type' => TypeRegistry::type(StoryType::class),
                    'resolve' => static fn (User $user): ?Story => DataSource::findLastStoryFor($user->id),
                ],
                'fieldWithError' => [
                    'type' => Type::string(),
                    'resolve' => static function (): void {
                        throw new \Exception('This is error field');
                    },
                ],
            ],
            'interfaces' => [TypeRegistry::type(NodeType::class)],
        ]);
    }
}
