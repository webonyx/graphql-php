<?php
namespace GraphQL\Examples\Blog\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Examples\Blog\Type\BaseType;
use GraphQL\Language\AST\StringValue;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Utils;

class EmailType extends BaseType
{
    public function __construct()
    {
        // Option #1: define scalar types using composition (see UrlType fo option #2 using inheritance)
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
     * @param string $value
     * @return string
     */
    public function serialize($value)
    {
        // Assuming internal representation of email is always correct:
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
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \UnexpectedValueException("Cannot represent value as email: " . Utils::printSafe($value));
        }
        return $value;
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input
     *
     * @param \GraphQL\Language\AST\Node $valueAST
     * @return string
     * @throws Error
     */
    public function parseLiteral($valueAST)
    {
        // Note: throwing GraphQL\Error\Error vs \UnexpectedValueException to benefit from GraphQL
        // error location in query:
        if (!$valueAST instanceof StringValue) {
            throw new Error('Query error: Can only parse strings got: ' . $valueAST->kind, [$valueAST]);
        }
        if (!filter_var($valueAST->value, FILTER_VALIDATE_EMAIL)) {
            throw new Error("Not a valid email", [$valueAST]);
        }
        return $valueAST->value;
    }
}
