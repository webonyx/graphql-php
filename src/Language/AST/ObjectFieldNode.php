<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ObjectFieldNode extends Node
{
    /** @var string */
    public $kind = NodeKind::OBJECT_FIELD;

    /** @var NameNode */
    public $name;

    /** @var VariableNode|NullValueNode|IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|EnumValueNode|ListValueNode|ObjectValueNode */
    public $value;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->value->value = $edits['value'] ?? $this->value->value;
    }
}
