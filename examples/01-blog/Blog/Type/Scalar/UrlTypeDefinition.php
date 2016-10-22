<?php
namespace GraphQL\Examples\Blog\Type\Scalar;

use GraphQL\Language\AST\StringValue;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils;

/**
 * Class UrlTypeDefinition
 *
 * @package GraphQL\Examples\Blog\Type\Scalar
 */
class UrlTypeDefinition extends ScalarType
{
    public $name = 'Url';

    /**
     * Serializes an internal value to include in a response.
     *
     * @param mixed $value
     * @return mixed
     */
    public function serialize($value)
    {
        return $this->coerceUrl($value);
    }

    /**
     * Parses an externally provided value to use as an input
     *
     * @param mixed $value
     * @return mixed
     */
    public function parseValue($value)
    {
        return $this->coerceUrl($value);
    }

    /**
     * @param $value
     * @return float|null
     */
    private function coerceUrl($value)
    {
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) { // quite naive, but after all this is example
            throw new \UnexpectedValueException("Cannot represent value as URL: " . Utils::printSafe($value));
        }
        return $value;
    }

    /**
     * @return null|string
     */
    public function parseLiteral($ast)
    {
        if ($ast instanceof StringValue) {
            return $ast->value;
        }
        return null;
    }
}
