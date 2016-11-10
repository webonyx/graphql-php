<?php
namespace GraphQL\Language\AST;

class FragmentSpread extends Node implements Selection
{
    public $kind = NodeType::FRAGMENT_SPREAD;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var array<Directive>
     */
    public $directives;
}
