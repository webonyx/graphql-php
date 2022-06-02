<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ObjectFieldNode extends Node
{
    public string $kind = NodeKind::OBJECT_FIELD;

    /** @var NameNode */
    public $name;

    /** @var VariableNode|NullValueNode|IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|EnumValueNode|ListValueNode|ObjectValueNode */
    public $value;
}
