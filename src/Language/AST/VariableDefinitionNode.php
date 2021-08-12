<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class VariableDefinitionNode extends Node implements DefinitionNode
{
    /** @var string */
    public $kind = NodeKind::VARIABLE_DEFINITION;

    /** @var VariableNode */
    public $variable;

    /** @var NamedTypeNode|ListTypeNode|NonNullTypeNode */
    public $type;

    /** @var VariableNode|NullValueNode|IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|EnumValueNode|ListValueNode|ObjectValueNode|null */
    public $defaultValue;

    /** @var NodeList<DirectiveNode> */
    public $directives;
}
