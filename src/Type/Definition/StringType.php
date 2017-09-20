<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class StringType
 * @package GraphQL\Type\Definition
 */
class StringType extends ScalarType
{
    /**
     * @var string
     */
    public $name = Type::STRING;

    /**
     * @var string
     */
    public $description =
'The `String` scalar type represents textual data, represented as UTF-8
character sequences. The String type is most often used by GraphQL to
represent free-form human-readable text.';

    /**
     * @param mixed $value
     * @return mixed|string
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
        if (!is_scalar($value)) {
            throw new InvariantViolation("String cannot represent non scalar value: " . Utils::printSafe($value));
        }
        return (string) $value;
    }

    /**
     * @param mixed $value
     * @return string
     */
    public function parseValue($value)
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @param $ast
     * @return null|string
     */
    public function parseLiteral($ast)
    {
        if ($ast instanceof StringValueNode) {
            return $ast->value;
        }
        return null;
    }
}
