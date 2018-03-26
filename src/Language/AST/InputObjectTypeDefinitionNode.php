<?php
namespace GraphQL\Language\AST;

class InputObjectTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    /**
     * @var string
     */
    public $kind = NodeKind::INPUT_OBJECT_TYPE_DEFINITION;

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

    /**
     * @var StringValueNode|null
     */
    public $description;
}
