<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\BooleanValue;

class BooleanType extends ScalarType
{
    public $name = Type::BOOLEAN;

    public function coerce($value)
    {
        return !!$value;
    }

    public function coerceLiteral($ast)
    {
        if ($ast instanceof BooleanValue) {
            return (bool) $ast->value;
        }
        return null;
    }
}
