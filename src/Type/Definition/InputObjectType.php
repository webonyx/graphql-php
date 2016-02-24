<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

class InputObjectType extends Type implements InputType
{
    /**
     * @var array<InputObjectField>
     */
    private $_fields;

    public $config;

    public function __construct(array $config)
    {
        Config::validate($config, [
            'name' => Config::STRING | Config::REQUIRED,
            'fields' => Config::arrayOf([
                'name' => Config::STRING | Config::REQUIRED,
                'type' => Config::INPUT_TYPE | Config::REQUIRED,
                'defaultValue' => Config::ANY,
                'description' => Config::STRING
            ], Config::KEY_AS_NAME),
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
        if (null === $this->_fields) {
            $this->_fields = [];
            $fields = isset($this->config['fields']) ? $this->config['fields'] : [];
            $fields = is_callable($fields) ? call_user_func($fields) : $fields;
            foreach ($fields as $name => $field) {
                $this->_fields[$name] = new InputObjectField($field + ['name' => $name]);
            }
        }

        return $this->_fields;
    }

    /**
     * @param string $name
     * @return InputObjectField
     * @throws \Exception
     */
    public function getField($name)
    {
        if (null === $this->_fields) {
            $this->getFields();
        }
        Utils::invariant(isset($this->_fields[$name]), "Field '%s' is not defined for type '%s'", $name, $this->name);
        return $this->_fields[$name];
    }
}
