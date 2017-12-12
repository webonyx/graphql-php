<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

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
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (!is_scalar($value) && (!is_object($value) || !method_exists($value, '__toString'))) {
            throw new InvariantViolation("ID type cannot represent non scalar value: " . Utils::printSafe($value));
        }
        return (string) $value;
    }

    /**
     * @param mixed $value
     * @return string
     */
    public function parseValue($value)
    {
        return (is_string($value) || is_int($value)) ? (string) $value : null;
    }

    /**
     * @param $ast
     * @return null|string
     */
    public function parseLiteral($ast)
    {
        if ($ast instanceof StringValueNode || $ast instanceof IntValueNode) {
            return $ast->value;
        }
        return null;
    }
}
