<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Utils\Utils;

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
     * @var ScalarTypeDefinitionNode|null
     */
    public $astNode;

    function __construct(array $config = [])
    {
        $this->name = isset($config['name']) ? $config['name'] : $this->tryInferName();
        $this->description = isset($config['description']) ? $config['description'] : $this->description;
        $this->astNode = isset($config['astNode']) ? $config['astNode'] : null;
        $this->config = $config;

        Utils::assertValidName($this->name);
    }

    /**
     * Determines if an internal value is valid for this type.
     * Equivalent to checking for if the parsedValue is nullish.
     *
     * @param $value
     * @return bool
     */
    public function isValidValue($value)
    {
        return null !== $this->parseValue($value);
    }

    /**
     * Determines if an internal value is valid for this type.
     * Equivalent to checking for if the parsedLiteral is nullish.
     *
     * @param $valueNode
     * @return bool
     */
    public function isValidLiteral($valueNode)
    {
        return null !== $this->parseLiteral($valueNode);
    }
}
