<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use UnexpectedValueException;

use function filter_var;

use const FILTER_VALIDATE_EMAIL;

class EmailType extends ScalarType
{
    public function serialize($value): string
    {
        // Assuming internal representation of email is always correct:
        return $value;

        // If it might be incorrect and you want to make sure that only correct values are included in response -
        // use following line instead:
        // return $this->parseValue($value);
    }

    public function parseValue($value)
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new UnexpectedValueException('Cannot represent value as email: ' . Utils::printSafe($value));
        }

        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        // Note: throwing GraphQL\Error\Error vs \UnexpectedValueException to benefit from GraphQL
        // error location in query:
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, [$valueNode]);
        }

        if (! filter_var($valueNode->value, FILTER_VALIDATE_EMAIL)) {
            throw new Error('Not a valid email', [$valueNode]);
        }

        return $valueNode->value;
    }
}
