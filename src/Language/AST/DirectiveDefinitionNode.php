<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class DirectiveDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    public string $kind = NodeKind::DIRECTIVE_DEFINITION;

    public NameNode $name;

    public ?StringValueNode $description = null;

    /** @var NodeList<InputValueDefinitionNode> */
    public NodeList $arguments;

    public bool $repeatable;

    /** @var NodeList<NameNode> */
    public NodeList $locations;
}
