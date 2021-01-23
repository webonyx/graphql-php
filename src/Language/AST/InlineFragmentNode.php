<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class InlineFragmentNode extends Node implements SelectionNode
{
    /** @var string */
    public $kind = NodeKind::INLINE_FRAGMENT;

    /** @var NamedTypeNode */
    public $typeCondition;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var SelectionSetNode */
    public $selectionSet;
}
