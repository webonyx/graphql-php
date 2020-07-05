<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class InlineFragmentNode extends Node implements SelectionNode
{
    /** @var string */
    public $kind = NodeKind::INLINE_FRAGMENT;

    /** @var NamedTypeNode */
    public $typeCondition;

    /** @var DirectiveNode[]|null */
    public $directives;

    /** @var SelectionSetNode */
    public $selectionSet;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->selectionSet = $edits['selectionSet'] ?? $this->selectionSet;
        $this->directives   = $edits['directives'] ?? $this->directives;
    }
}
