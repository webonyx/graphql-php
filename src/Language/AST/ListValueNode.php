<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ListValueNode extends Node implements ValueNode
{
    /** @var string */
    public $kind = NodeKind::LST;

    /** @var NodeList<ValueNode&Node> */
    public $values;
}
