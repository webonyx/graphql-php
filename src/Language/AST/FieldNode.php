<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class FieldNode extends Node implements SelectionNode
{
    /** @var string */
    public $kind = NodeKind::FIELD;

    /** @var NameNode */
    public $name;

    /** @var NameNode|null */
    public $alias;

    /** @var NodeList<ArgumentNode>|null */
    public $arguments;

    /** @var NodeList<DirectiveNode>|null */
    public $directives;

    /** @var SelectionSetNode|null */
    public $selectionSet;
}
