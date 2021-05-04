<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Data;

use GraphQL\Utils\Utils;

class Story
{
    public int $id;

    public int $authorId;

    public string $title;

    public string $body;

    public bool $isAnonymous = false;

    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
