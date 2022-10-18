<?php

declare(strict_types=1);

namespace GraphQL;

interface Resolvable
{
    public function resolveGraphQLType(): string;
}
