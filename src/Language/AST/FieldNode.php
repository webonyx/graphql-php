<?php
namespace GraphQL\Language\AST;

class FieldNode extends Node implements SelectionNode
{
    public $kind = NodeKind::FIELD;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var NameNode|null
     */
    public $alias;

    /**
     * @var ArgumentNode[]|null
     */
    public $arguments;

    /**
     * @var DirectiveNode[]|null
     */
    public $directives;

    /**
     * @var SelectionSetNode|null
     */
    public $selectionSet;
}
