<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Utils\Utils;

/**
 * Class BooleanType
 * @package GraphQL\Type\Definition
 */
class BooleanType extends ScalarType
{
    /**
     * @var string
     */
    public $name = Type::BOOLEAN;

    /**
     * @var string
     */
    public $description = 'The `Boolean` scalar type represents `true` or `false`.';

    /**
     * @param mixed $value
     * @return bool
     */
    public function serialize($value)
    {
        return !!$value;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function parseValue($value)
    {
        return is_bool($value) ? $value : Utils::undefined();
    }

    /**
     * @param $valueNode
     * @param array|null $variables
     * @return bool|null
     */
    public function parseLiteral($valueNode, array $variables = null)
    {
        if ($valueNode instanceof BooleanValueNode) {
            return (bool) $valueNode->value;
        }
        return Utils::undefined();
    }
}
