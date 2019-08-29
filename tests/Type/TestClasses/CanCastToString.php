<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

class CanCastToString
{
    /** @var string */
    public $str = '';

    public function __construct($str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}
