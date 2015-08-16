<?php
namespace GraphQL\Language\AST;


class FragmentDefinition extends NamedType implements Definition
{
    public $kind = Node::FRAGMENT_DEFINITION;

    /**
     * @var NamedType
     */
    public $typeCondition;

    /**
     * @var array<Directive>
     */
    public $directives;

    /**
     * @var SelectionSet
     */
    public $selectionSet;
}
