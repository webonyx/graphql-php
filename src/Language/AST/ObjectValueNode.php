<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ObjectValueNode extends Node implements ValueNode
{
    /** @var string */
    public $kind = NodeKind::OBJECT;

    /** @var NodeList<ObjectFieldNode> */
    public $fields;
}
