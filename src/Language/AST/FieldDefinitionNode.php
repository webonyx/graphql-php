<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class FieldDefinitionNode extends Node
{
    /** @var string */
    public $kind = NodeKind::FIELD_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<InputValueDefinitionNode> */
    public $arguments;

    /** @var NamedTypeNode|ListTypeNode|NonNullTypeNode */
    public $type;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var StringValueNode|null */
    public $description;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->arguments = $edits['arguments'] ?? $this->arguments;

        // TODO: don't overwrite description with string
        $this->description = $edits['description'] ?? $this->description;
    }
}
