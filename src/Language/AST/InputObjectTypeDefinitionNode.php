<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class InputObjectTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    /** @var string */
    public $kind = NodeKind::INPUT_OBJECT_TYPE_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var DirectiveNode[]|null */
    public $directives;

    /** @var InputValueDefinitionNode[]|null */
    public $fields;

    /** @var StringValueNode|null */
    public $description;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->directives = $edits['directives'] ?? $this->directives;
        $this->fields = $edits['fields'] ?? $this->fields;
    }
}
