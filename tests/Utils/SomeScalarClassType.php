<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;

/**
 * Custom class-based scalar type for testing.
 */
class SomeScalarClassType extends ScalarType
{
    public function serialize($value): string
    {
        return $value;
    }
    public function parseValue($value): string
    {
        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        return $valueNode->value;
    }
}
