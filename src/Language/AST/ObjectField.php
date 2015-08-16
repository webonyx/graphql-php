<?php
namespace GraphQL\Language\AST;


class ObjectField extends NamedType
{
    public $kind = Node::OBJECT_FIELD;

    /**
     * @var Value
     */
    public $value;
}
