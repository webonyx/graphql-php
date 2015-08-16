<?php
namespace GraphQL\Language\AST;

class FragmentSpread extends NamedType
{
    public $kind = Node::FRAGMENT_SPREAD;

    /**
     * @var array<Directive>
     */
    public $directives;
}
