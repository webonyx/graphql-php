<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class InputObjectTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public string $kind = NodeKind::INPUT_OBJECT_TYPE_DEFINITION;

    public NameNode $name;

    /** @var NodeList<DirectiveNode> */
    public NodeList $directives;

    /** @var NodeList<InputValueDefinitionNode> */
    public NodeList $fields;

    public ?StringValueNode $description = null;
}
