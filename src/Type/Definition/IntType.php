<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\Value;

class IntType extends ScalarType
{
    public $name = Type::INT;

    public function serialize($value)
    {
        return $this->coerceInt($value);
    }

    public function parseValue($value)
    {
        return $this->coerceInt($value);
    }

    private function coerceInt($value)
    {
        if (false === $value || true === $value) {
            return (int) $value;
        }
        if (is_numeric($value) && $value <= PHP_INT_MAX && $value >= -1 * PHP_INT_MAX) {
            return (int) $value;
        }
        return null;
    }

    public function parseLiteral($ast)
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
