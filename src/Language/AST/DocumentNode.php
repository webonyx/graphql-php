<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

class DocumentNode extends Node
{
    public string $kind = NodeKind::DOCUMENT;

    /** @var NodeList<DefinitionNode&Node> */
    public NodeList $definitions;
}
