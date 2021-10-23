<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class StringValueNode extends Node implements ValueNode
{
    public string $kind = NodeKind::STRING;

    /** @var string */
    public $value;

    /** @var bool */
    public $block;
}
