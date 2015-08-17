<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\BooleanValue;

class BooleanType extends ScalarType
{
    public $name = Type::BOOLEAN;

    public function serialize($value)
    {
        return !!$value;
    }

    public function parseValue($value)
    {
        return !!$value;
    }

    public function parseLiteral($ast)
    {
        if ($ast instanceof BooleanValue) {
            return (bool) $ast->value;
        }
        return null;
    }
}
