<?php
namespace GraphQL\Type\Definition;

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
     * Parses an externally provided value to use as an input
     *
     * @param mixed $value
     * @return mixed
     */
    public function parseValue($value);

    /**
     * Parses an externally provided literal value to use as an input
     *
     * @param \GraphQL\Language\AST\Value $valueAST
     * @return mixed
     */
    public function parseLiteral($valueAST);
}
