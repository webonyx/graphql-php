<?php

namespace GraphQL\Language\AST;

class ListValueNode extends Node implements ValueNode
{
    public $kind = NodeType::LST;

    /**
     * @var array<ValueNode>
     */
    public $values;
}
