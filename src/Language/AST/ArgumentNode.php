<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ArgumentNode extends Node
{
    /** @var string */
    public $kind = NodeKind::ARGUMENT;

    /** @var VariableNode|NullValueNode|IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|EnumValueNode|ListValueNode|ObjectValueNode */
    public $value;

    /** @var NameNode */
    public $name;
}
