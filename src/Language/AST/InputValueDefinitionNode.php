<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class InputValueDefinitionNode extends Node
{
    /** @var string */
    public $kind = NodeKind::INPUT_VALUE_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var NamedTypeNode|ListTypeNode|NonNullTypeNode */
    public $type;

    /** @var VariableNode|NullValueNode|IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|EnumValueNode|ListValueNode|ObjectValueNode|null */
    public $defaultValue;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var StringValueNode|null */
    public $description;
}
