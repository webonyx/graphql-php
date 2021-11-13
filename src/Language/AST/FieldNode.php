<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class FieldNode extends Node implements SelectionNode
{
    public string $kind = NodeKind::FIELD;

    public NameNode $name;

    public ?NameNode $alias = null;

    /** @var NodeList<ArgumentNode> */
    public NodeList $arguments;

    /** @var NodeList<DirectiveNode> */
    public NodeList $directives;

    public ?SelectionSetNode $selectionSet = null;
}
