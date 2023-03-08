<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * @phpstan-type ValueNodeVariants VariableNode|NullValueNode|IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|EnumValueNode|ListValueNode|ObjectValueNode
export type ValueNode =
| VariableNode
| IntValueNode
| FloatValueNode
| StringValueNode
| BooleanValueNode
| NullValueNode
| EnumValueNode
| ListValueNode
| ObjectValueNode;
 */
interface ValueNode
{
}
