<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ListTypeNode extends Node implements TypeNode
{
    /** @var string */
    public $kind = NodeKind::LIST_TYPE;

    /** @var NamedTypeNode|ListTypeNode|NonNullTypeNode */
    public $type;

    public function setEdits(array $edits) {
        // TODO: figure this out. We shouldn't let Visitor stomp over $type with a string, but if we don't, the printer breaks.
        $this->type = $edits['type'] ?? $this->type;
    }
}
