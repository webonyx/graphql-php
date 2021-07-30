<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Utils\Utils;

use function is_bool;

class BooleanType extends ScalarType
{
    public $name = Type::BOOLEAN;

    public $description = 'The `Boolean` scalar type represents `true` or `false`.';

    /**
     * Serialize the given value to a Boolean.
     *
     * The GraphQL spec leaves this up to the implementations, so we just do what
     * PHP does natively to make this intuitive for developers.
     */
    public function serialize($value): bool
    {
        return (bool) $value;
    }

    public function parseValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        throw new Error('Boolean cannot represent a non boolean value: ' . Utils::printSafe($value));
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): bool
    {
        if ($valueNode instanceof BooleanValueNode) {
            return $valueNode->value;
        }

        // Intentionally without message, as all information already in wrapped Exception
        throw new Error();
    }
}
