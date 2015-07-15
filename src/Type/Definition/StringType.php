<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\StringValue;

class StringType extends ScalarType
{
    public $name = Type::STRING;

    public function coerce($value)
    {
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        return (string) $value;
    }

    public function coerceLiteral($ast)
    {
        if ($ast instanceof StringValue) {
            return $ast->value;
        }
        return null;
    }
}
