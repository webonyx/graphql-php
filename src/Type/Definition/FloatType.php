<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\FloatValue;
use GraphQL\Language\AST\IntValue;

class FloatType extends ScalarType
{
    public $name = Type::FLOAT;

    public function coerce($value)
    {
        return is_numeric($value) || $value === true || $value === false ? (float) $value : null;
    }

    public function coerceLiteral($ast)
    {
        if ($ast instanceof FloatValue || $ast instanceof IntValue) {
            return (float) $ast->value;
        }
        return null;
    }
}
