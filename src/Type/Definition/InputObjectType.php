<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Utils\Utils;

/**
 * Class InputObjectType
 * @package GraphQL\Type\Definition
 */
class InputObjectType extends Type implements InputType, NamedType
{
    /**
     * @var InputObjectField[]
     */
    private $fields;

    /**
     * @var InputObjectTypeDefinitionNode|null
     */
    public $astNode;

    /**
     * InputObjectType constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

        Utils::invariant(is_string($config['name']), 'Must provide name.');

        $this->config = $config;
        $this->name = $config['name'];
        $this->astNode = isset($config['astNode']) ? $config['astNode'] : null;
        $this->description = isset($config['description']) ? $config['description'] : null;
    }

    /**
     * @return InputObjectField[]
     */
    public function getFields()
    {
        if (null === $this->fields) {
            $this->fields = [];
            $fields = isset($this->config['fields']) ? $this->config['fields'] : [];
            $fields = is_callable($fields) ? call_user_func($fields) : $fields;

            if (!is_array($fields)) {
                throw new InvariantViolation(
                    "{$this->name} fields must be an array or a callable which returns such an array."
                );
            }

            foreach ($fields as $name => $field) {
                if ($field instanceof Type) {
                    $field = ['type' => $field];
                }
                $field = new InputObjectField($field + ['name' => $name]);
                $this->fields[$field->name] = $field;
            }
        }

        return $this->fields;
    }

    /**
     * @param string $name
     * @return InputObjectField
     * @throws \Exception
     */
    public function getField($name)
    {
        if (null === $this->fields) {
            $this->getFields();
        }
        Utils::invariant(isset($this->fields[$name]), "Field '%s' is not defined for type '%s'", $name, $this->name);
        return $this->fields[$name];
    }
}
