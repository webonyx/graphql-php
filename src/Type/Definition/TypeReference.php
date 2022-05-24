<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

class TypeReference extends Type implements NullableType, NamedType
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function assertValid(): void
    {
    }

    public function isBuiltInType(): bool
    {
    }

    public function toString(): string
    {
        return $this->name;
    }
}
