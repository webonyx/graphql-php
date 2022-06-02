<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class DirectiveNode extends Node
{
    public string $kind = NodeKind::DIRECTIVE;

    /** @var NameNode */
    public $name;

    /** @var NodeList<ArgumentNode> */
    public $arguments;
}
