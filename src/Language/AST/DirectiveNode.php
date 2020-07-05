<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class DirectiveNode extends Node
{
    /** @var string */
    public $kind = NodeKind::DIRECTIVE;

    /** @var NameNode */
    public $name;

    /** @var ArgumentNode[] */
    public $arguments;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->arguments = $edits['arguments'] ?? $this->arguments;
    }
}
