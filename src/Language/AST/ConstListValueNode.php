<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

class ConstListValueNode extends Node implements ValueNode, ConstValueNode
{
    public string $kind = NodeKind::LST;

    /** @var NodeList<ConstValueNode&Node> */
    public NodeList $values;
}
