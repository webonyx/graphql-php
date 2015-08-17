<?php
namespace GraphQL\Type\Definition;

class InputObjectType extends Type implements InputType
{
    /**
     * @var array<InputObjectField>
     */
    private $_fields = [];

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

        if (!empty($config['fields'])) {
            foreach ($config['fields'] as $name => $field) {
                $this->_fields[$name] = new InputObjectField($field + ['name' => $name]);
            }
        }

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
    }

    /**
     * @return InputObjectField[]
     */
    public function getFields()
    {
        return $this->_fields;
    }
}
