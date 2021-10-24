<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ScalarTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public string $kind = NodeKind::SCALAR_TYPE_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var StringValueNode|null */
    public $description;
}
