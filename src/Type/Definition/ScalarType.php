<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

/**
 * Scalar Type Definition
 *
 * The leaf values of any request and input values to arguments are
 * Scalars (or Enums) and are defined with a name and a series of coercion
 * functions used to ensure validity.
 *
 * Example:
 *
 * class OddType extends ScalarType
 * {
 *     public $name = 'Odd',
 *     public function serialize($value)
 *     {
 *         return $value % 2 === 1 ? $value : null;
 *     }
 * }
 */
abstract class ScalarType extends Type implements OutputType, InputType, LeafType
{
    /**
     * ScalarType constructor.
     */
    public function __construct()
    {
        if (!isset($this->name)) {
            $this->name = $this->tryInferName();
        }

        Utils::invariant($this->name, 'Type must be named.');
    }

    /**
     * Serializes an internal value to include in a response.
     *
     * @param mixed $value
     * @return mixed
     */
    abstract public function serialize($value);

    /**
     * Parses an externally provided value to use as an input
     *
     * @param mixed $value
     * @return mixed
     */
    abstract public function parseValue($value);

    /**
     * Parses an externally provided literal value to use as an input
     *
     * @param \GraphQL\Language\AST\Value $valueAST
     * @return mixed
     */
    abstract public function parseLiteral($valueAST);
}
