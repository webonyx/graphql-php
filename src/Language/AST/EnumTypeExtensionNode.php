<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class EnumTypeExtensionNode extends Node implements TypeExtensionNode
{
    public string $kind = NodeKind::ENUM_TYPE_EXTENSION;

    public NameNode $name;

    /** @var NodeList<DirectiveNode> */
    public NodeList $directives;

    /** @var NodeList<EnumValueDefinitionNode> */
    public NodeList $values;
}
