<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * @phpstan-type ArgumentNodeValue VariableNode|NullValueNode|IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|EnumValueNode|ListValueNode|ObjectValueNode
 */
class ArgumentNode extends Node
{
    public string $kind = NodeKind::ARGUMENT;

    /** @phpstan-var ArgumentNodeValue */
    public ValueNode $value;

    public NameNode $name;
}
