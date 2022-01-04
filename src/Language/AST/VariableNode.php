<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class VariableNode extends Node implements ValueNode
{
    public string $kind = NodeKind::VARIABLE;

    public NameNode $name;
}
