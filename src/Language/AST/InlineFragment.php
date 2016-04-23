<?php
namespace GraphQL\Language\AST;

class InlineFragment extends Node implements Selection
{
    public $kind = Node::INLINE_FRAGMENT;

    /**
     * @var NamedType
     */
    public $typeCondition;

    /**
     * @var array<Directive>|null
     */
    public $directives;

    /**
     * @var SelectionSet
     */
    public $selectionSet;
}