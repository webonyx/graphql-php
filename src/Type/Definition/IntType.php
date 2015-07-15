<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\IntValue;

class IntType extends ScalarType
{
    public $name = Type::INT;

    public function coerce($value)
    {
        if (false === $value || true === $value) {
            return (int) $value;
        }
        if (is_numeric($value) && $value <= PHP_INT_MAX && $value >= -1 * PHP_INT_MAX) {
            return (int) $value;
        }
        return null;
    }

    public function coerceLiteral($ast)
    {
        if ($ast instanceof IntValue) {
            $val = (int) $ast->value;
            if ($ast->value === (string) $val) {
                return $val;
            }
        }
        return null;
    }
}
