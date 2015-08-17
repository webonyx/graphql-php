<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\FloatValue;
use GraphQL\Language\AST\IntValue;

class FloatType extends ScalarType
{
    public $name = Type::FLOAT;

    public function serialize($value)
    {
        return $this->coerceFloat($value);
    }

    public function parseValue($value)
    {
        return $this->coerceFloat($value);
    }

    private function coerceFloat($value)
    {
        return is_numeric($value) || $value === true || $value === false ? (float) $value : null;
    }

    public function parseLiteral($ast)
    {
        if ($ast instanceof FloatValue || $ast instanceof IntValue) {
            return (float) $ast->value;
        }
        return null;
    }
}
