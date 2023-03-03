<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

class EnumValueNode extends Node implements ValueNode, ConstValueNode
{
    public string $kind = NodeKind::ENUM;

    public string $value;
}
