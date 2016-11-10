<?php

namespace GraphQL\Language\AST;

class DirectiveDefinition extends Node implements TypeSystemDefinition
{
    /**
     * @var string
     */
    public $kind = Node::DIRECTIVE_DEFINITION;

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
