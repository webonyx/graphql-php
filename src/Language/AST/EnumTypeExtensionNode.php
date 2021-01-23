<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class EnumTypeExtensionNode extends Node implements TypeExtensionNode
{
    /** @var string */
    public $kind = NodeKind::ENUM_TYPE_EXTENSION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var NodeList<EnumValueDefinitionNode> */
    public $values;
}
