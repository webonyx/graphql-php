<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Utils\Utils;

/**
 * Class InputObjectType
 * @package GraphQL\Type\Definition
 */
class InputObjectType extends Type implements InputType
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

        Utils::assertValidName($config['name']);

        Config::validate($config, [
            'name' => Config::NAME | Config::REQUIRED,
            'fields' => Config::arrayOf([
                'name' => Config::NAME | Config::REQUIRED,
                'type' => Config::INPUT_TYPE | Config::REQUIRED,
                'defaultValue' => Config::ANY,
                'description' => Config::STRING
            ], Config::KEY_AS_NAME | Config::MAYBE_THUNK | Config::MAYBE_TYPE),
            'description' => Config::STRING
        ]);

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

    /**
     * @throws InvariantViolation
     */
    public function assertValid()
    {
        parent::assertValid();

        $fields = $this->getFields();

        Utils::invariant(
            !empty($fields),
            "{$this->name} fields must not be empty"
        );

        foreach ($fields as $field) {
            try {
                Utils::assertValidName($field->name);
            } catch (InvariantViolation $e) {
                throw new InvariantViolation("{$this->name}.{$field->name}: {$e->getMessage()}");
            }

            $fieldType = $field->type;
            if ($fieldType instanceof WrappingType) {
                $fieldType = $fieldType->getWrappedType(true);
            }
            Utils::invariant(
                $fieldType instanceof InputType,
                "{$this->name}.{$field->name} field type must be Input Type but got: %s.",
                Utils::printSafe($field->type)
            );
            Utils::invariant(
                !isset($field->config['resolve']),
                "{$this->name}.{$field->name} field type has a resolve property, but Input Types cannot define resolvers."
            );
        }
    }
}
