<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils\SchemaExtenderTest;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;

/** Custom class-based scalar type for testing. */
final class SomeScalarClassType extends ScalarType
{
    public const SERIALIZE_RETURN = 'a constant value that is always returned from serialize';
    public const PARSE_VALUE_RETURN = 'a constant value that is always returned from parseValue';
    public const PARSE_LITERAL_RETURN = 'a constant value that is always returned from parseLiteral';

    public function serialize($value): string
    {
        return self::SERIALIZE_RETURN;
    }

    public function parseValue($value): string
    {
        return self::PARSE_VALUE_RETURN;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        return self::PARSE_LITERAL_RETURN;
    }
}
