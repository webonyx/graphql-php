<?php
namespace GraphQL\Examples\Blog\Type\Scalar;

use GraphQL\Language\AST\StringValue;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils;

class EmailType extends ScalarType
{
    public $name = 'Email';

    public static function create()
    {
        return new self();
    }

    /**
     * Serializes an internal value to include in a response.
     *
     * @param mixed $value
     * @return mixed
     */
    public function serialize($value)
    {
        return $this->coerceEmail($value);
    }

    /**
     * Parses an externally provided value to use as an input
     *
     * @param mixed $value
     * @return mixed
     */
    public function parseValue($value)
    {
        return $this->coerceEmail($value);
    }

    private function coerceEmail($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \UnexpectedValueException("Cannot represent value as email: " . Utils::printSafe($value));
        }
        return $value;
    }

    /**
     * Parses an externally provided literal value to use as an input
     *
     * @param \GraphQL\Language\AST\Value $valueAST
     * @return mixed
     */
    public function parseLiteral($valueAST)
    {
        if ($valueAST instanceof StringValue) {
            return $valueAST->value;
        }
        return null;
    }
}
