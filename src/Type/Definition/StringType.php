<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\StringValueNode;

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
        if ($ast instanceof StringValueNode) {
            return $ast->value;
        }
        return null;
    }
}
