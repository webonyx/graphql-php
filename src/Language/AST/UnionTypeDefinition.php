<?php
namespace GraphQL\Language\AST;

class UnionTypeDefinition extends Node implements TypeDefinition
{
    /**
     * @var string
     */
    public $kind = self::UNION_TYPE_DEFINITION;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var NamedType[]
     */
    public $types = [];
}
