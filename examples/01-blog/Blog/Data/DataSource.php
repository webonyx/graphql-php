<?php
namespace GraphQL\Examples\Blog\Data;
use GraphQL\Utils;

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
    private $comments = [];

    public function __construct()
    {
        $this->users = [
            '1' => new User([
                'id' => '1',
                'email' => 'john@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe'
            ]),
            '2' => new User([
                'id' => '2',
                'email' => 'jane@example.com',
                'firstName' => 'Jane',
                'lastName' => 'Doe'
            ]),
            '3' => new User([
                'id' => '3',
                'email' => 'john@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe'
            ]),
        ];

        $this->stories = [
            '1' => new Story(['id' => '1', 'authorId' => '1', 'body' => '<h1>GraphQL is awesome!</h1>']),
            '2' => new Story(['id' => '2', 'authorId' => '1', 'body' => '<a>Test this</a>']),
            '3' => new Story(['id' => '3', 'authorId' => '3', 'body' => "This\n<br>story\n<br>spans\n<br>newlines"]),
        ];

        $this->storyLikes = [
            '1' => ['1', '2', '3'],
            '2' => [],
            '3' => ['1']
        ];

        $this->comments = [
            // thread #1:
            '100' => new Comment(['id' => '100', 'authorId' => '3', 'storyId' => '1', 'body' => 'Likes']),
                '110' => new Comment(['id' =>'110', 'authorId' =>'2', 'storyId' => '1', 'body' => 'Reply <b>#1</b>', 'parentId' => '100']),
                    '111' => new Comment(['id' => '111', 'authorId' => '1', 'storyId' => '1', 'body' => 'Reply #1-1', 'parentId' => '110']),
                    '112' => new Comment(['id' => '112', 'authorId' => '3', 'storyId' => '1', 'body' => 'Reply #1-2', 'parentId' => '110']),
                    '113' => new Comment(['id' => '113', 'authorId' => '2', 'storyId' => '1', 'body' => 'Reply #1-3', 'parentId' => '110']),
                    '114' => new Comment(['id' => '114', 'authorId' => '1', 'storyId' => '1', 'body' => 'Reply #1-4', 'parentId' => '110']),
                    '115' => new Comment(['id' => '115', 'authorId' => '3', 'storyId' => '1', 'body' => 'Reply #1-5', 'parentId' => '110']),
                    '116' => new Comment(['id' => '116', 'authorId' => '1', 'storyId' => '1', 'body' => 'Reply #1-6', 'parentId' => '110']),
                    '117' => new Comment(['id' => '117', 'authorId' => '2', 'storyId' => '1', 'body' => 'Reply #1-7', 'parentId' => '110']),
                '120' => new Comment(['id' => '120', 'authorId' => '3', 'storyId' => '1', 'body' => 'Reply #2', 'parentId' => '100']),
                '130' => new Comment(['id' => '130', 'authorId' => '3', 'storyId' => '1', 'body' => 'Reply #3', 'parentId' => '100']),
            '200' => new Comment(['id' => '200', 'authorId' => '2', 'storyId' => '1', 'body' => 'Me2']),
            '300' => new Comment(['id' => '300', 'authorId' => '3', 'storyId' => '1', 'body' => 'U2']),

            # thread #2:
            '400' => new Comment(['id' => '400', 'authorId' => '2', 'storyId' => '2', 'body' => 'Me too']),
            '500' => new Comment(['id' => '500', 'authorId' => '2', 'storyId' => '2', 'body' => 'Nice!']),
        ];

        $this->storyComments = [
            '1' => ['100', '200', '300'],
            '2' => ['400', '500']
        ];

        $this->commentReplies = [
            '100' => ['110', '120', '130'],
            '110' => ['111', '112', '113', '114', '115', '116', '117'],
        ];

        $this->storyMentions = [
            '1' => [
                $this->users['2']
            ],
            '2' => [
                $this->stories['1'],
                $this->users['3']
            ]
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

    public function isLikedBy($storyId, $userId)
    {
        $subscribers = isset($this->storyLikes[$storyId]) ? $this->storyLikes[$storyId] : [];
        return in_array($userId, $subscribers);
    }

    public function getUserPhoto($userId, $size)
    {
        return new Image([
            'id' => $userId,
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

    public function findStories($limit, $afterId = null)
    {
        $start = $afterId ? (int) array_search($afterId, array_keys($this->stories)) + 1 : 0;
        return array_slice(array_values($this->stories), $start, $limit);
    }

    public function findComments($storyId, $limit = 5, $afterId = null)
    {
        $storyComments = isset($this->storyComments[$storyId]) ? $this->storyComments[$storyId] : [];

        $start = isset($after) ? (int) array_search($afterId, $storyComments) + 1 : 0;
        $storyComments = array_slice($storyComments, $start, $limit);

        return array_map(
            function($commentId) {
                return $this->comments[$commentId];
            },
            $storyComments
        );
    }

    public function findReplies($commentId, $limit = 5, $afterId = null)
    {
        $commentReplies = isset($this->commentReplies[$commentId]) ? $this->commentReplies[$commentId] : [];

        $start = isset($after) ? (int) array_search($afterId, $commentReplies) + 1: 0;
        $commentReplies = array_slice($commentReplies, $start, $limit);

        return array_map(
            function($replyId) {
                return $this->comments[$replyId];
            },
            $commentReplies
        );
    }

    public function countComments($storyId)
    {
        return isset($this->storyComments[$storyId]) ? count($this->storyComments[$storyId]) : 0;
    }

    public function countReplies($commentId)
    {
        return isset($this->commentReplies[$commentId]) ? count($this->commentReplies[$commentId]) : 0;
    }

    public function findStoryMentions($storyId)
    {
        return isset($this->storyMentions[$storyId]) ? $this->storyMentions[$storyId] :[];
    }
}
