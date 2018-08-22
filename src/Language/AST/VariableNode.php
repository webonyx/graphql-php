<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class VariableNode extends Node
{
    /** @var string */
    public $kind = NodeKind::VARIABLE;

    /** @var NameNode */
    public $name;
}
