<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class Special
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}
