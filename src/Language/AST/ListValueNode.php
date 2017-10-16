<?php

namespace GraphQL\Language\AST;

class ListValueNode extends Node implements ValueNode
{
    public $kind = NodeKind::LST;

    /**
     * @var ValueNode[]
     */
    public $values;
}
