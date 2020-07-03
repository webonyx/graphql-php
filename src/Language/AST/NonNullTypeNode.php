<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class NonNullTypeNode extends Node implements TypeNode
{
    /** @var string */
    public $kind = NodeKind::NON_NULL_TYPE;

    /** @var NamedTypeNode | ListTypeNode */
    public $type;

    public function setEdits(array $edits) {
        // TODO: figure this out. We shouldn't let Visitor stomp over $type with a string, but if we don't, the printer breaks.
        $this->type = $edits['type'] ?? $this->type;
    }
}
