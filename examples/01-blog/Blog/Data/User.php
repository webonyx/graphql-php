<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Data;

use GraphQL\Utils\Utils;

class User
{
    public int $id;

    public string $email;

    public string $firstName;

    public string $lastName;

    public bool $hasPhoto;

    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
