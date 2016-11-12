<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\StringValue;

/**
 * Class IDType
 * @package GraphQL\Type\Definition
 */
class IDType extends ScalarType
{
    /**
     * @var string
     */
    public $name = 'ID';

    /**
     * @var string
     */
    public $description =
'The `ID` scalar type represents a unique identifier, often used to
refetch an object or as key for a cache. The ID type appears in a JSON
response as a String; however, it is not intended to be human-readable.
When expected as an input type, any string (such as `"4"`) or integer
(such as `4`) input value will be accepted as an ID.';

    /**
     * @param mixed $value
     * @return string
     */
    public function serialize($value)
    {
        return $this->parseValue($value);
    }

    /**
     * @param mixed $value
     * @return string
     */
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

    /**
     * @param $ast
     * @return null|string
     */
    public function parseLiteral($ast)
    {
        if ($ast instanceof StringValue || $ast instanceof IntValue) {
            return $ast->getValue();
        }
        return null;
    }
}
