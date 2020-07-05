<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class UnionTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    /** @var string */
    public $kind = NodeKind::UNION_TYPE_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var DirectiveNode[] */
    public $directives;

    /** @var NamedTypeNode[]|null */
    public $types;

    /** @var StringValueNode|null */
    public $description;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->directives = $edits['directives'] ?? $this->directives;
        $this->types = $edits['types'] ?? $this->types;
    }
}
