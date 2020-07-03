<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class NamedTypeNode extends Node implements TypeNode
{
    /** @var string */
    public $kind = NodeKind::NAMED_TYPE;

    /** @var NameNode|string */
    public $name;

    public function setEdits(array $edits) {
        $this->name->value = $edits['name'] ?? $this->name->value;
    }
}
