<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\Node;
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
     * @throws Error
     */
    public function parseValue($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        throw new Error("Cannot represent value as boolean: " . Utils::printSafe($value));
    }

    /**
     * @param Node $valueNode
     * @param array|null $variables
     * @return bool|null
     * @throws \Exception
     */
    public function parseLiteral($valueNode, array $variables = null)
    {
        if ($valueNode instanceof BooleanValueNode) {
            return (bool) $valueNode->value;
        }

        // Intentionally without message, as all information already in wrapped Exception
        throw new \Exception();
    }
}
