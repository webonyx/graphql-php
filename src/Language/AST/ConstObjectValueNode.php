<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

class ConstObjectValueNode extends Node implements ValueNode, ConstValueNode
{
    public string $kind = NodeKind::OBJECT;

    /** @var NodeList<ConstObjectFieldNode> */
    public NodeList $fields;
}
