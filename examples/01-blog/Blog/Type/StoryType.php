<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Type\Enum\StoryAffordancesType;
use GraphQL\Examples\Blog\Type\Field\HtmlField;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;

class StoryType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'Story',
            'fields' => static fn (): array => [
                'id' => Types::id(),
                'author' => [
                    'type' => Types::user(),
                    'resolve' => static fn (Story $story): ?User => DataSource::findUser($story->authorId),
                ],
                'mentions' => [
                    'type' => new ListOfType(Types::mention()),
                    'resolve' => static fn (Story $story): array => DataSource::findStoryMentions($story->id),
                ],
                'totalCommentCount' => [
                    'type' => Types::int(),
                    'resolve' => static fn (Story $story): int => DataSource::countComments($story->id),
                ],
                'comments' => [
                    'type' => new ListOfType(Types::comment()),
                    'args' => [
                        'after' => [
                            'type' => Types::id(),
                            'description' => 'Load all comments listed after given comment ID',
                        ],
                        'limit' => [
                            'type' => Types::int(),
                            'defaultValue' => 5,
                        ],
                    ],
                    'resolve' => static fn (Story $story, array $args): array => DataSource::findComments(
                        $story->id,
                        $args['limit'],
                        isset($args['after'])
                            ? (int) $args['after']
                            : null
                    ),
                ],
                'likes' => [
                    'type' => new ListOfType(Types::user()),
                    'args' => [
                        'limit' => [
                            'type' => Types::int(),
                            'description' => 'Limit the number of recent likes returned',
                            'defaultValue' => 5,
                        ],
                    ],
                    'resolve' => static fn (Story $story): array => DataSource::findLikes($story->id, 10),
                ],
                'likedBy' => [
                    'type' => new ListOfType(Types::user()),
                    'resolve' => static fn (Story $story) => DataSource::findLikes($story->id, 10),
                ],
                'affordances' => [
                    'type' => new ListOfType(Types::storyAffordances()),
                    'resolve' => function (Story $story, array $args, AppContext $context): array {
                        /** @var array<int, string> $affordances */
                        $affordances = [];

                        $isViewer = $context->viewer === DataSource::findUser($story->authorId);
                        if ($isViewer) {
                            $affordances[] = StoryAffordancesType::EDIT;
                            $affordances[] = StoryAffordancesType::EDIT;
                            $affordances[] = StoryAffordancesType::DELETE;
                        }

                        $affordances[] = DataSource::isLikedBy($story->id, $context->viewer->id)
                            ? StoryAffordancesType::UNLIKE
                            : StoryAffordancesType::LIKE;

                        return $affordances;
                    },
                ],
                'hasViewerLiked' => [
                    'type' => Types::boolean(),
                    'resolve' => static fn (Story $story, array $args, AppContext $context): bool => DataSource::isLikedBy($story->id, $context->viewer->id),
                ],
                'body' => HtmlField::build([
                    'resolve' => static fn (Story $story): string => $story->body,
                ]),
            ],
            'interfaces' => [Types::node()],
        ]);
    }
}
