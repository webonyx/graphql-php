<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class NotSpecial
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}
