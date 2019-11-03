<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class DocumentNode extends Node
{
    /** @var string */
    public $kind = NodeKind::DOCUMENT;

    /** @var NodeList|array<DefinitionNode> */
    public $definitions;
}
