<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

use function assert;
use function in_array;

class OtherEnum
{
    /** @var string */
    private $value;

    public function __construct(string $value)
    {
        assert(in_array($value, ['ONE', 'TWO', 'THREE'], true));

        $this->value = $value;
    }

    public function getValue() : string
    {
        return $this->value;
    }
}
