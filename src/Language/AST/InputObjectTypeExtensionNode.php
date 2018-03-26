<?php
namespace GraphQL\Language\AST;

class InputObjectTypeExtensionNode extends Node implements TypeExtensionNode
{
    /**
     * @var string
     */
    public $kind = NodeKind::INPUT_OBJECT_TYPE_EXTENSION;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var DirectiveNode[]|null
     */
    public $directives;

    /**
     * @var InputValueDefinitionNode[]|null
     */
    public $fields;
}
