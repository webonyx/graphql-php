<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

#[\AllowDynamicProperties]
class DocumentNode extends Node
{
    /** @var string */
    public $kind = NodeKind::DOCUMENT;

    /** @var NodeList<DefinitionNode&Node> */
    public $definitions;
}
