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

    /** @var DirectiveNode[] */
    public $directives;

    /** @var StringValueNode|null */
    public $description;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        // TODO: figure out type
        $this->type       = $edits['type'] ?? $this->type;
        $this->directives = $edits['directives'] ?? $this->directives;

        // TODO: figure out defaultValue
        $this->defaultValue = $edits['defaultValue'] ?? $this->defaultValue;
    }
}
