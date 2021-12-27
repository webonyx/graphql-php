<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type;

use GraphQL\Examples\Blog\AppContext;
use GraphQL\Examples\Blog\Data\Comment;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\Story;
use GraphQL\Examples\Blog\Data\User;
use GraphQL\Examples\Blog\Type\Field\HtmlField;
use GraphQL\Examples\Blog\Types;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

use function method_exists;
use function ucfirst;

class StoryType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'Story',
            'fields' => static fn (): array => [
                'id' => Types::id(),
                'author' => Types::user(),
                'mentions' => new ListOfType(Types::mention()),
                'totalCommentCount' => Types::int(),
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
                ],
                'likedBy' => new ListOfType(Types::user()),
                'affordances' => new ListOfType(Types::storyAffordances()),
                'hasViewerLiked' => Types::boolean(),

                'body' => HtmlField::build('body'),
            ],
            'interfaces' => [Types::node()],
            'resolveField' => function (Story $story, array $args, $context, ResolveInfo $info) {
                $fieldName = $info->fieldName;

                $method = 'resolve' . ucfirst($fieldName);
                if (method_exists($this, $method)) {
                    return $this->{$method}($story, $args, $context, $info);
                }

                return $story->{$fieldName};
            },
        ]);
    }

    public function resolveAuthor(Story $story): ?User
    {
        return DataSource::findUser($story->authorId);
    }

    /**
     * @param array<never> $args
     *
     * @return array<int, string>
     */
    public function resolveAffordances(Story $story, array $args, AppContext $context): array
    {
        /** @var array<int, string> $affordances */
        $affordances = [];

        $isViewer = $context->viewer === DataSource::findUser($story->authorId);
        if ($isViewer) {
            $affordances[] = Enum\StoryAffordancesType::EDIT;
            $affordances[] = Enum\StoryAffordancesType::EDIT;
            $affordances[] = Enum\StoryAffordancesType::DELETE;
        }

        $isLiked = DataSource::isLikedBy($story->id, $context->viewer->id);
        if ($isLiked) {
            $affordances[] = Enum\StoryAffordancesType::UNLIKE;
        } else {
            $affordances[] = Enum\StoryAffordancesType::LIKE;
        }

        return $affordances;
    }

    /**
     * @param array<never> $args
     */
    public function resolveHasViewerLiked(Story $story, array $args, AppContext $context): bool
    {
        return DataSource::isLikedBy($story->id, $context->viewer->id);
    }

    public function resolveTotalCommentCount(Story $story): int
    {
        return DataSource::countComments($story->id);
    }

    /**
     * @param array{limit: int, after?: string} $args
     *
     * @return array<int, Comment>
     */
    public function resolveComments(Story $story, array $args): array
    {
        return DataSource::findComments(
            $story->id,
            $args['limit'],
            isset($args['after'])
                ? (int) $args['after']
                : null
        );
    }

    /**
     * @return array<int, Story|User>
     */
    public function resolveMentions(Story $story): array
    {
        return DataSource::findStoryMentions($story->id);
    }

    /**
     * @return array<int, User>
     */
    public function resolveLikedBy(Story $story): array
    {
        return DataSource::findLikes($story->id, 10);
    }

    /**
     * @return array<int, User>
     */
    public function resolveLikes(Story $story): array
    {
        return DataSource::findLikes($story->id, 10);
    }
}
