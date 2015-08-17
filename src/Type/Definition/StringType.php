<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\StringValue;

class StringType extends ScalarType
{
    public $name = Type::STRING;

    public function serialize($value)
    {
        return $this->parseValue($value);
    }

    public function parseValue($value)
    {
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        return (string) $value;
    }

    public function parseLiteral($ast)
    {
        if ($ast instanceof StringValue) {
            return $ast->value;
        }
        return null;
    }
}
