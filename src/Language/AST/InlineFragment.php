<?php
namespace GraphQL\Language\AST;

class InlineFragment extends Node
{
    public $kind = Node::INLINE_FRAGMENT;

    /**
     * @var Name
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