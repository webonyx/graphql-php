<?php
namespace GraphQL\Type\Definition;

use \GraphQL\Language\AST\Node;

/*
export type GraphQLLeafType =
GraphQLScalarType |
GraphQLEnumType;
*/
interface LeafType
{
    /**
     * Serializes an internal value to include in a response.
     *
     * @param mixed $value
     * @return mixed
     */
    public function serialize($value);

    /**
     * Parses an externally provided value (query variable) to use as an input
     *
     * In the case of an invalid value this method must return Utils::undefined()
     *
     * @param mixed $value
     * @return mixed
     */
    public function parseValue($value);

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input
     *
     * In the case of an invalid value this method must return Utils::undefined()
     *
     * @param Node $valueNode
     * @param array|null $variables
     * @return mixed
     */
    public function parseLiteral($valueNode, array $variables = null);
}
