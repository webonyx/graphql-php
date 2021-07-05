<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use Exception;
use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class QueryType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'Query',
            'fields' => [
                'user' => [
                    'type' => Types::user(),
                    'description' => 'Returns user by id (in range of 1-5)',
                    'args' => [
                        'id' => Types::nonNull(Types::id()),
                    ],
                ],
                'viewer' => [
                    'type' => Types::user(),
                    'description' => 'Represents currently logged-in user (for the sake of example - simply returns user with id == 1)',
                ],
                'stories' => [
                    'type' => Types::listOf(Types::story()),
                    'description' => 'Returns subset of stories posted for this blog',
                    'args' => [
                        'after' => [
                            'type' => Types::id(),
                            'description' => 'Fetch stories listed after the story with this ID',
                        ],
                        'limit' => [
                            'type' => Types::int(),
                            'description' => 'Number of stories to be returned',
                            'defaultValue' => 10,
                        ],
                    ],
                ],
                'lastStoryPosted' => [
                    'type' => Types::story(),
                    'description' => 'Returns last story posted for this blog',
                ],
                'deprecatedField' => [
                    'type' => Types::string(),
                    'deprecationReason' => 'This field is deprecated!',
                ],
                'fieldWithException' => [
                    'type' => Types::string(),
                    'resolve' => static function (): void {
                        throw new Exception('Exception message thrown in field resolver');
                    },
                ],
                'hello' => Type::string(),
            ],
            'resolveField' => function ($rootValue, $args, $context, ResolveInfo $info) {
                return $this->{$info->fieldName}($rootValue, $args, $context, $info);
            },
        ]);
    }

    /**
     * @param null              $rootValue
     * @param array{id: string} $args
     */
    public function user($rootValue, array $args): ?User
    {
        return DataSource::findUser((int) $args['id']);
    }

    /**
     * @param null         $rootValue
     * @param array<never> $args
     */
    public function viewer($rootValue, array $args, AppContext $context): User
    {
        return $context->viewer;
    }

    /**
     * @param null                           $rootValue
     * @param array{limit: int, after?: string} $args
     *
     * @return array<int, Story>
     */
    public function stories($rootValue, array $args): array
    {
        return DataSource::findStories($args['limit'], (int) $args['after'] ?? null);
    }

    public function lastStoryPosted(): ?Story
    {
        return DataSource::findLatestStory();
    }

    public function hello(): string
    {
        return 'Your graphql-php endpoint is ready! Use a GraphQL client to explore the schema.';
    }

    public function deprecatedField(): string
    {
        return 'You can request deprecated field, but it is not displayed in auto-generated documentation by default.';
    }
}
