<?php
namespace GraphQL\Examples\Blog\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValue;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils;

class UrlType extends ScalarType
{
    // Option #2: Displays scalar type defined using inheritance. See EmailType for definition via composition

    /**
     * Serializes an internal value to include in a response.
     *
     * @param mixed $value
     * @return mixed
     */
    public function serialize($value)
    {
        // Assuming internal representation of url is always correct:
        return $value;

        // If it might be incorrect and you want to make sure that only correct values are included in response -
        // use following line instead:
        // return $this->parseValue($value);
    }

    /**
     * Parses an externally provided value (query variable) to use as an input
     *
     * @param mixed $value
     * @return mixed
     */
    public function parseValue($value)
    {
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) { // quite naive, but after all this is example
            throw new \UnexpectedValueException("Cannot represent value as URL: " . Utils::printSafe($value));
        }
        return $value;
    }

    /**
     * Parses an externally provided literal value to use as an input (e.g. in Query AST)
     *
     * @param $ast Node
     * @return null|string
     * @throws Error
     */
    public function parseLiteral($ast)
    {
        // Note: throwing GraphQL\Error\Error vs \UnexpectedValueException to benefit from GraphQL
        // error location in query:
        if (!($ast instanceof StringValue)) {
            throw new Error('Query error: Can only parse strings got: ' . $ast->getKind(), [$ast]);
        }
        if (!is_string($ast->getValue()) || !filter_var($ast->getValue(), FILTER_VALIDATE_URL)) {
            throw new Error('Query error: Not a valid URL', [$ast]);
        }
        return $ast->getValue();
    }
}
