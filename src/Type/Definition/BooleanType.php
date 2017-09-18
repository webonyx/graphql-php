<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\BooleanValueNode;

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
        return is_bool($value) ? $value : null;
    }

    /**
     * @param $ast
     * @return bool|null
     */
    public function parseLiteral($ast)
    {
        if ($ast instanceof BooleanValueNode) {
            return (bool) $ast->value;
        }
        return null;
    }
}
