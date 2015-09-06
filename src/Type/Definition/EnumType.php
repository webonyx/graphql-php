<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\EnumValue;
use GraphQL\Utils;

class EnumType extends Type implements InputType, OutputType
{
    /**
     * @var array<EnumValueDefinition>
     */
    private $_values;

    /**
     * @var \ArrayObject<mixed, EnumValueDefinition>
     */
    private $_valueLookup;

    /**
     * @var \ArrayObject<string, EnumValueDefinition>
     */
    private $_nameLookup;

    public function __construct($config)
    {
        Config::validate($config, [
            'name' => Config::STRING | Config::REQUIRED,
            'values' => Config::arrayOf([
                'name' => Config::STRING | Config::REQUIRED,
                'value' => Config::ANY,
                'deprecationReason' => Config::STRING,
                'description' => Config::STRING
            ], Config::KEY_AS_NAME),
            'description' => Config::STRING
        ]);

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->_values = [];

        if (!empty($config['values'])) {
            foreach ($config['values'] as $name => $value) {
                $this->_values[] = Utils::assign(new EnumValueDefinition(), $value + ['name' => $name, 'value' => $name]); // value will be equal to name only if 'value'  is not set in definition
            }
        }
    }

    /**
     * @return array<EnumValueDefinition>
     */
    public function getValues()
    {
        return $this->_values;
    }

    /**
     * @param $value
     * @return null
     */
    public function serialize($value)
    {
        $lookup = $this->_getValueLookup();
        return isset($lookup[$value]) ? $lookup[$value]->name : null;
    }

    /**
     * @param $value
     * @return null
     */
    public function parseValue($value)
    {
        $lookup = $this->_getNameLookup();
        return isset($lookup[$value]) ? $lookup[$value]->value : null;
    }

    /**
     * @param $value
     * @return null
     */
    public function parseLiteral($value)
    {
        if ($value instanceof EnumValue) {
            $lookup = $this->_getNameLookup();
            if (isset($lookup[$value->value])) {
                $enumValue = $lookup[$value->value];
                if ($enumValue) {
                    return $enumValue->value;
                }
            }
        }
        return null;
    }

    /**
     * @todo Value lookup for any type, not just scalars
     * @return \ArrayObject<mixed, EnumValueDefinition>
     */
    protected function _getValueLookup()
    {
        if (null === $this->_valueLookup) {
            $this->_valueLookup = new \ArrayObject();

            foreach ($this->getValues() as $valueName => $value) {
                $this->_valueLookup[$value->value] = $value;
            }
        }

        return $this->_valueLookup;
    }

    /**
     * @return \ArrayObject<string, GraphQLEnumValueDefinition>
     */
    protected function _getNameLookup()
    {
        if (!$this->_nameLookup) {
            $lookup = new \ArrayObject();
            foreach ($this->getValues() as $value) {
                $lookup[$value->name] = $value;
            }
            $this->_nameLookup = $lookup;
        }
        return $this->_nameLookup;
    }
}
