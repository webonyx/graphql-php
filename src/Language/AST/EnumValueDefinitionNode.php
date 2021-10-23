<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class EnumValueDefinitionNode extends Node
{
    public string $kind = NodeKind::ENUM_VALUE_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var StringValueNode|null */
    public $description;
}
