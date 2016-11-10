<?php

namespace GraphQL\Language\AST;

class DirectiveDefinition extends Node implements TypeSystemDefinition
{
    /**
     * @var string
     */
    protected $kind = Node::DIRECTIVE_DEFINITION;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Argument[]
     */
    public $arguments;

    /**
     * @var Name[]
     */
    public $locations;
}
