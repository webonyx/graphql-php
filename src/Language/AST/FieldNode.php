<?php
namespace GraphQL\Language\AST;

class FieldNode extends Node implements SelectionNode
{
    public $kind = NodeType::FIELD;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var NameNode|null
     */
    public $alias;

    /**
     * @var array<ArgumentNode>|null
     */
    public $arguments;

    /**
     * @var array<DirectiveNode>|null
     */
    public $directives;

    /**
     * @var SelectionSetNode|null
     */
    public $selectionSet;
}
