<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class SelectionSetNode extends Node
{
    /** @var string */
    public $kind = NodeKind::SELECTION_SET;

    /** @var NodeList<SelectionSetNode> */
    public $selections;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->selections = $edits['selections'] ?? $this->selections;
    }
}
