<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\StringValue;

class IDType extends ScalarType
{
    public $name = 'ID';

    public $description =
'The `ID` scalar type represents a unique identifier, often used to
refetch an object or as key for a cache. The ID type appears in a JSON
response as a String; however, it is not intended to be human-readable.
When expected as an input type, any string (such as `"4"`) or integer
(such as `4`) input value will be accepted as an ID.';

    public function serialize($value)
    {
        return (string) $value;
    }

    public function parseValue($value)
    {
        return (string) $value;
    }

    public function parseLiteral($ast)
    {
        if ($ast instanceof StringValue || $ast instanceof IntValue) {
            return $ast->value;
        }
        return null;
    }
}
