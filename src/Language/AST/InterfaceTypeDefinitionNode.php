<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class InterfaceTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    /** @var string */
    public $kind = NodeKind::INTERFACE_TYPE_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var DirectiveNode[]|null */
    public $directives;

    /** @var FieldDefinitionNode[]|null */
    public $fields;

    /** @var StringValueNode|null */
    public $description;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->directives = $edits['directives'] ?? $this->directives;
        $this->fields     = $edits['fields'] ?? $this->fields;
    }
}
