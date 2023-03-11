<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

class IntValueNode extends Node implements ValueNode, ConstValueNode
{
    public string $kind = NodeKind::INT;

    public string $value;
}
