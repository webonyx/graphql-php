<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
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
                        'id' => new NonNull(Types::id()),
                    ],
                    'resolve' => static fn ($rootValue, array $args): ?User => DataSource::findUser((int) $args['id']),
                ],
                'viewer' => [
                    'type' => Types::user(),
                    'description' => 'Represents currently logged-in user (for the sake of example - simply returns user with id == 1)',
                ],
                'stories' => [
                    'type' => new ListOfType(Types::story()),
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
                    'resolve' => static fn ($rootValue, $args): array => DataSource::findStories(
                        $args['limit'],
                        isset($args['after'])
                            ? (int) $args['after']
                            : null
                    ),
                ],
                'lastStoryPosted' => [
                    'type' => Types::story(),
                    'description' => 'Returns last story posted for this blog',
                    'resolve' => static fn (): ?Story => DataSource::findLatestStory(),
                ],
                'deprecatedField' => [
                    'type' => Types::string(),
                    'deprecationReason' => 'This field is deprecated!',
                    'resolve' => static fn (): string => 'You can request deprecated field, but it is not displayed in auto-generated documentation by default.',
                ],
                'fieldWithException' => [
                    'type' => Types::string(),
                    'resolve' => static function (): void {
                        throw new \Exception('Exception message thrown in field resolver');
                    },
                ],
                'hello' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => 'Your graphql-php endpoint is ready! Use a GraphQL client to explore the schema.',
                ],
            ],
        ]);
    }
}
