<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class DirectiveNode extends Node
{
    public string $kind = NodeKind::DIRECTIVE;

    public NameNode $name;

    /** @var NodeList<ArgumentNode> */
    public NodeList $arguments;
}
