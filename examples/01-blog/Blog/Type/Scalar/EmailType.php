<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use UnexpectedValueException;

use function filter_var;

use const FILTER_VALIDATE_EMAIL;

class EmailType extends ScalarType
{
    /**
     * Serializes an internal value to include in a response.
     *
     * Should throw an exception on invalid values.
     *
     * @param mixed $value
     */
    public function serialize($value): string
    {
        if (! $this->isEmail($value)) {
            throw new UnexpectedValueException('Cannot represent value as email: ' . Utils::printSafe($value));
        }

        return $value;
    }

    /**
     * Parses an externally provided value (query variable) to use as an input.
     *
     * Should throw an exception with a client friendly message on invalid values, @see ClientAware.
     *
     * @param mixed $value
     */
    public function parseValue($value): string
    {
        if (! $this->isEmail($value)) {
            throw new Error('Cannot represent value as email: ' . Utils::printSafe($value));
        }

        return $value;
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
     *
     * Should throw an exception with a client friendly message on invalid value nodes, @see ClientAware.
     *
     * @param IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|NullValueNode $valueNode
     * @param array<string, mixed>|null                                                  $variables
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        // Note: throwing GraphQL\Error\Error vs \UnexpectedValueException to benefit from GraphQL
        // error location in query:
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, [$valueNode]);
        }

        $value = $valueNode->value;
        if (! $this->isEmail($value)) {
            throw new Error('Not a valid email', [$valueNode]);
        }

        return $value;
    }

    /**
     * Is the value a valid email?
     *
     * @param mixed $value
     */
    private function isEmail($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
