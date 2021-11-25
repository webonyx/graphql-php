<?php

declare(strict_types=1);

namespace GraphQL\Server;

class ValidOperation
{
    public string $query;

    public ?string $operation;

    /** @var array<string, mixed>|null */
    public ?array $variables;

    /** @var array<string, mixed>|null */
    public ?array $extensions;

    public bool $readOnly;
}
