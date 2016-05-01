<?php
namespace GraphQL\Type\Definition;


use GraphQL\Utils;

class InterfaceType extends Type implements AbstractType, OutputType, CompositeType
{
    /**
     * @var array<string,FieldDefinition>
     */
    private $_fields;

    public $description;

    /**
     * @var callback
     */
    private $_resolveTypeFn;

    /**
     * @var array
     */
    public $config;

    /**
     * InterfaceType constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        Config::validate($config, [
            'name' => Config::STRING,
            'fields' => Config::arrayOf(
                FieldDefinition::getDefinition(),
                Config::KEY_AS_NAME | Config::MAYBE_THUNK
            ),
            'resolveType' => Config::CALLBACK, // function($value, $context, ResolveInfo $info) => ObjectType
            'description' => Config::STRING
        ]);

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->_resolveTypeFn = isset($config['resolveType']) ? $config['resolveType'] : null;
        $this->config = $config;
    }

    /**
     * @return array<FieldDefinition>
     */
    public function getFields()
    {
        if (null === $this->_fields) {
            $this->_fields = [];
            $fields = isset($this->config['fields']) ? $this->config['fields'] : [];
            $fields = is_callable($fields) ? call_user_func($fields) : $fields;
            $this->_fields = FieldDefinition::createMap($fields);
        }
        return $this->_fields;
    }

    /**
     * @param $name
     * @return FieldDefinition
     * @throws \Exception
     */
    public function getField($name)
    {
        if (null === $this->_fields) {
            $this->getFields();
        }
        Utils::invariant(isset($this->_fields[$name]), 'Field "%s" is not defined for type "%s"', $name, $this->name);
        return $this->_fields[$name];
    }

    /**
     * @return callable|null
     */
    public function getResolveTypeFn()
    {
        return $this->_resolveTypeFn;
    }
}
