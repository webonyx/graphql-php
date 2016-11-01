<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

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
     * @var array
     */
    public $config;

    /**
     * InputObjectType constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

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
