<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ObjectTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    /** @var string */
    public $kind = NodeKind::OBJECT_TYPE_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var NamedTypeNode[] */
    public $interfaces = [];

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
        $this->interfaces = $edits['interfaces'] ?? $this->interfaces;
        $this->directives = $edits['directives'] ?? $this->directives;
        $this->fields     = $edits['fields'] ?? $this->fields;
    }
}
