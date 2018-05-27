<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
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
     * @throws Error
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
            throw new Error("String cannot represent non scalar value: " . Utils::printSafe($value));
        }
        return $this->coerceString($value);
    }

    /**
     * @param mixed $value
     * @return string
     * @throws Error
     */
    public function parseValue($value)
    {
        return $this->coerceString($value);
    }

    /**
     * @param $valueNode
     * @param array|null $variables
     * @return null|string
     * @throws \Exception
     */
    public function parseLiteral($valueNode, array $variables = null)
    {
        if ($valueNode instanceof StringValueNode) {
            return $valueNode->value;
        }

        // Intentionally without message, as all information already in wrapped Exception
        throw new \Exception();
    }

    private function coerceString($value) {
        if (is_array($value)) {
            throw new Error(
                'String cannot represent an array value: ' .
                Utils::printSafe($value)
            );
        }

        return (string) $value;
    }
}
