<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class NotSpecial
{
    /** @var string */
    public $value;

    /** @param string $value */
    public function __construct($value)
    {
        $this->value = $value;
    }
}
