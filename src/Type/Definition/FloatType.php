<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Utils\Utils;

use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_numeric;

class FloatType extends ScalarType
{
    public $name = Type::FLOAT;

    public $description =
        'The `Float` scalar type represents signed double-precision fractional
values as specified by
[IEEE 754](http://en.wikipedia.org/wiki/IEEE_floating_point). ';

    public function serialize($value): float
    {
        $float = is_numeric($value) || is_bool($value)
            ? (float) $value
            : null;

        if ($float === null || ! is_finite($float)) {
            throw new SerializationError(
                'Float cannot represent non numeric value: ' .
                Utils::printSafe($value)
            );
        }

        return $float;
    }

    public function parseValue($value): float
    {
        $float = is_float($value) || is_int($value)
            ? (float) $value
            : null;

        if ($float === null || ! is_finite($float)) {
            throw new Error(
                'Float cannot represent non numeric value: ' .
                Utils::printSafe($value)
            );
        }

        return $float;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof FloatValueNode || $valueNode instanceof IntValueNode) {
            return (float) $valueNode->value;
        }

        // Intentionally without message, as all information already in wrapped Exception
        throw new Error();
    }
}
