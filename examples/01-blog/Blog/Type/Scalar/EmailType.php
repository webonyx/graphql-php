<?php
namespace GraphQL\Examples\Blog\Type\Scalar;

use GraphQL\Examples\Blog\Type\BaseType;
use GraphQL\Language\AST\StringValue;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Utils;

class EmailType extends BaseType
{
    public function __construct()
    {
        $this->definition = new CustomScalarType([
            'name' => 'Email',
            'serialize' => [$this, 'serialize'],
            'parseValue' => [$this, 'parseValue'],
            'parseLiteral' => [$this, 'parseLiteral'],
        ]);
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
