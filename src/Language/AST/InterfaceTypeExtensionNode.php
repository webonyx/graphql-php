<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class InterfaceTypeExtensionNode extends Node implements TypeExtensionNode
{
    /** @var string */
    public $kind = NodeKind::INTERFACE_TYPE_EXTENSION;

    /** @var NameNode */
    public $name;

    /** @var DirectiveNode[]|null */
    public $directives;

    /** @var FieldDefinitionNode[]|null */
    public $fields;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->directives = $edits['directives'] ?? $this->directives;
        $this->fields = $edits['fields'] ?? $this->fields;
    }
}
