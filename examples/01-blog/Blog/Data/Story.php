<?php
namespace GraphQL\Examples\Blog\Data;

use GraphQL\Utils;

class Story
{
    public $id;

    public $authorId;

    public $title;

    public $body;

    public $isAnonymous = false;

    public $isLiked = false;

    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
