<?php

declare(strict_types=1);

namespace GraphQL\Server;

class PersistedOperation
{
    public string $queryId;

    public ?string $operation;

    /** @var array<string, mixed>|null */
    public ?array $variables;

    /** @var array<string, mixed>|null */
    public ?array $extensions;

    public bool $readOnly;
}
