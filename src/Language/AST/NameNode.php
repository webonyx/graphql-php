<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class NameNode extends Node implements TypeNode
{
    public string $kind = NodeKind::NAME;

    /** @var string */
    public $value;
}
