<?php
namespace GraphQL\Examples\Blog\Data;

/**
 * Class DataSource
 *
 * This is just a simple in-memory data holder for the sake of example.
 * Data layer for real app may use Doctrine or query the database directly (e.g. in CQRS style)
 *
 * @package GraphQL\Examples\Blog
 */
class DataSource
{
    private $users = [];

    private $stories = [];

    private $storyLikes = [];

    public function __construct()
    {
        $this->users = [
            1 => new User([
                'id' => 1,
                'email' => 'john@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe'
            ]),
            2 => new User([
                'id' => 2,
                'email' => 'jane@example.com',
                'firstName' => 'Jane',
                'lastName' => 'Doe'
            ]),
            3 => new User([
                'id' => 3,
                'email' => 'john@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe'
            ]),
        ];

        $this->stories = [
            1 => new Story(['id' => 1, 'authorId' => 1]),
            2 => new Story(['id' => 2, 'authorId' => 1]),
            3 => new Story(['id' => 3, 'authorId' => 3]),
        ];

        $this->storyLikes = [
            1 => [1, 2, 3],
            2 => [],
            3 => [1]
        ];
    }

    public function findUser($id)
    {
        return isset($this->users[$id]) ? $this->users[$id] : null;
    }

    public function findStory($id)
    {
        return isset($this->stories[$id]) ? $this->stories[$id] : null;
    }

    public function findLastStoryFor($authorId)
    {
        $storiesFound = array_filter($this->stories, function(Story $story) use ($authorId) {
            return $story->authorId == $authorId;
        });
        return !empty($storiesFound) ? $storiesFound[count($storiesFound) - 1] : null;
    }

    public function isLikedBy(Story $story, User $user)
    {
        $subscribers = isset($this->storyLikes[$story->id]) ? $this->storyLikes[$story->id] : [];
        return in_array($user->id, $subscribers);
    }

    public function getUserPhoto(User $user, $size)
    {
        return new Image([
            'id' => $user->id,
            'type' => Image::TYPE_USERPIC,
            'size' => $size,
            'width' => rand(100, 200),
            'height' => rand(100, 200)
        ]);
    }

    public function findLatestStory()
    {
        return array_pop($this->stories);
    }
}
