<?php

namespace GraphQL\Language\AST;

class FragmentSpread extends Node implements Selection
{
    protected $kind = NodeType::FRAGMENT_SPREAD;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var array<Directive>
     */
    public $directives;
}
