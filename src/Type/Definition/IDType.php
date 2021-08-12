<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

use function is_int;
use function is_object;
use function is_string;
use function method_exists;

class IDType extends ScalarType
{
    public string $name = 'ID';

    public ?string $description =
        'The `ID` scalar type represents a unique identifier, often used to
refetch an object or as key for a cache. The ID type appears in a JSON
response as a String; however, it is not intended to be human-readable.
When expected as an input type, any string (such as `"4"`) or integer
(such as `4`) input value will be accepted as an ID.';

    public function serialize($value): string
    {
        $canCast = is_string($value)
            || is_int($value)
            || (is_object($value) && method_exists($value, '__toString'));

        if (! $canCast) {
            throw new SerializationError('ID cannot represent value: ' . Utils::printSafe($value));
        }

        return (string) $value;
    }

    public function parseValue($value): string
    {
        if (is_string($value) || is_int($value)) {
            return (string) $value;
        }

        throw new Error('ID cannot represent value: ' . Utils::printSafe($value));
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if ($valueNode instanceof StringValueNode || $valueNode instanceof IntValueNode) {
            return $valueNode->value;
        }

        // Intentionally without message, as the wrapping Exception will have all necessary information
        throw new Error();
    }
}
