<?php declare(strict_types=1);

namespace GraphQL\Examples\Blog\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class UrlType extends ScalarType
{
    /** @throws SerializationError */
    public function serialize($value): string
    {
        if (! $this->isUrl($value)) {
            $notUrl = Utils::printSafe($value);
            throw new SerializationError("Cannot represent value as URL: {$notUrl}");
        }

        return $value;
    }

    /** @throws Error */
    public function parseValue($value): string
    {
        if (! $this->isUrl($value)) {
            $notUrl = Utils::printSafeJson($value);
            throw new Error("Cannot represent value as URL: {$notUrl}");
        }

        return $value;
    }

    public function parseLiteral(Node $valueNode, array $variables = null): string
    {
        // Throwing GraphQL\Error\Error to benefit from GraphQL error location in query
        if (! ($valueNode instanceof StringValueNode)) {
            throw new Error("Query error: Can only parse strings got: {$valueNode->kind}", [$valueNode]);
        }

        $value = $valueNode->value;
        if (! $this->isUrl($value)) {
            throw new Error('Query error: Not a valid URL', [$valueNode]);
        }

        return $value;
    }

    /**
     * Is the given value a valid URL?
     *
     * @param mixed $value
     */
    private function isUrl($value): bool
    {
        return \is_string($value)
            && \filter_var($value, \FILTER_VALIDATE_URL) !== false;
    }
}
