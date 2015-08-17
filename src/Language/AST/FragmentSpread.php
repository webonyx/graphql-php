<?php
namespace GraphQL\Language\AST;

class FragmentSpread extends Node
{
    public $kind = Node::FRAGMENT_SPREAD;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var array<Directive>
     */
    public $directives;
}
