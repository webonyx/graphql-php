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
abstract class ScalarType extends Type implements OutputType, InputType, LeafType, NamedType
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

        Utils::invariant(is_string($this->name), 'Must provide name.');
    }
}
