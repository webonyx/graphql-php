<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\StringValueNode;

/*
export type GraphQLLeafType =
GraphQLScalarType |
GraphQLEnumType;
*/

/**
 * @phpstan-type LeafValueNode IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|NullValueNode|EnumValueNode
 */
interface LeafType
{
    /**
     * Serializes an internal value to include in a response.
     *
     * Should throw an exception on invalid values.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function serialize($value);

    /**
     * Parses an externally provided value (query variable) to use as an input.
     *
     * Should throw an exception with a client friendly message on invalid values, @see ClientAware.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function parseValue($value);

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
     *
     * Should throw an exception with a client friendly message on invalid value nodes, @see ClientAware.
     *
     * @param array<string, mixed>|null $variables
     * @phpstan-param LeafValueNode    $valueNode
     *
     * @return mixed
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null);
}
