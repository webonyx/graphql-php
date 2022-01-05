<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Data;

use GraphQL\Utils\Utils;

class Comment
{
    public int $id;

    public int $authorId;

    public int $storyId;

    public ?int $parentId = null;

    public string $body;

    public bool $isAnonymous = true;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
