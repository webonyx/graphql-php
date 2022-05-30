<?php
namespace GraphQL\Examples\Blog\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Utils\Utils;

class EmailType extends CustomScalarType
{
    public function __construct(array $config = [])
    {
        parent::__construct([
            'serialize' => [__CLASS__, 's_serialize'],
            'parseValue' => [__CLASS__, 's_parseValue'],
            'parseLiteral' => [__CLASS__, 's_parseLiteral'],
        ]);
    }

    /**
     * Serializes an internal value to include in a response.
     *
     * @param string $value
     * @return string
     */
    public static function s_serialize($value)
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
    public static function s_parseValue($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \UnexpectedValueException("Cannot represent value as email: " . Utils::printSafe($value));
        }
        return $value;
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input
     *
     * @param \GraphQL\Language\AST\Node $valueNode
     * @return string
     * @throws Error
     */
    public static function s_parseLiteral($valueNode)
    {
        // Note: throwing GraphQL\Error\Error vs \UnexpectedValueException to benefit from GraphQL
        // error location in query:
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, [$valueNode]);
        }
        if (!filter_var($valueNode->value, FILTER_VALIDATE_EMAIL)) {
            throw new Error("Not a valid email", [$valueNode]);
        }
        return $valueNode->value;
    }
}
