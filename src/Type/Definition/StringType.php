<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\Printer;
use GraphQL\Utils\Utils;
use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;

class StringType extends ScalarType
{
    public string $name = Type::STRING;

    public ?string $description
        = 'The `String` scalar type represents textual data, represented as UTF-8
character sequences. The String type is most often used by GraphQL to
represent free-form human-readable text.';

    public function serialize($value): string
    {
        $canCast = is_scalar($value)
            || (is_object($value) && method_exists($value, '__toString'))
            || null === $value;

        if (! $canCast) {
            throw new SerializationError(
                'String cannot represent value: ' . Utils::printSafe($value)
            );
        }

        return (string) $value;
    }

    public function parseValue($value): string
    {
        if (! is_string($value)) {
            $notString = Utils::printSafe($value);
            throw new Error("String cannot represent a non string value: {$notString}");
        }

        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if ($valueNode instanceof StringValueNode) {
            return $valueNode->value;
        }

        $notString = Printer::doPrint($valueNode);
        throw new Error("String cannot represent a non string value: {$notString}", $valueNode);
    }
}
