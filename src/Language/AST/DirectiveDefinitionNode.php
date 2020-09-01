<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class DirectiveDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    /** @var string */
    public $kind = NodeKind::DIRECTIVE_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var StringValueNode|null */
    public $description;

    /** @var NodeList<InputValueDefinitionNode> */
    public $arguments;

    /** @var bool */
    public $repeatable;

    /** @var NodeList<NameNode> */
    public $locations;
}
