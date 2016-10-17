<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\EnumValue;
use GraphQL\Utils;

/**
 * Class EnumType
 * @package GraphQL\Type\Definition
 */
class EnumType extends Type implements InputType, OutputType, LeafType
{
    /**
     * @var array<EnumValueDefinition>
     */
    private $values;

    /**
     * @var \ArrayObject<mixed, EnumValueDefinition>
     */
    private $valueLookup;

    /**
     * @var \ArrayObject<string, EnumValueDefinition>
     */
    private $nameLookup;

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
        $this->values = [];

        if (!empty($config['values'])) {
            foreach ($config['values'] as $name => $value) {
                $this->values[] = Utils::assign(new EnumValueDefinition(), $value + ['name' => $name, 'value' => $name]); // value will be equal to name only if 'value'  is not set in definition
            }
        }
    }

    /**
     * @return array<EnumValueDefinition>
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param $value
     * @return null
     */
    public function serialize($value)
    {
        $lookup = $this->getValueLookup();
        return isset($lookup[$value]) ? $lookup[$value]->name : null;
    }

    /**
     * @param $value
     * @return null
     */
    public function parseValue($value)
    {
        $lookup = $this->getNameLookup();
        return isset($lookup[$value]) ? $lookup[$value]->value : null;
    }

    /**
     * @param $value
     * @return null
     */
    public function parseLiteral($value)
    {
        if ($value instanceof EnumValue) {
            $lookup = $this->getNameLookup();
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
    private function getValueLookup()
    {
        if (null === $this->valueLookup) {
            $this->valueLookup = new \ArrayObject();

            foreach ($this->getValues() as $valueName => $value) {
                $this->valueLookup[$value->value] = $value;
            }
        }

        return $this->valueLookup;
    }

    /**
     * @return \ArrayObject<string, GraphQLEnumValueDefinition>
     */
    private function getNameLookup()
    {
        if (!$this->nameLookup) {
            $lookup = new \ArrayObject();
            foreach ($this->getValues() as $value) {
                $lookup[$value->name] = $value;
            }
            $this->nameLookup = $lookup;
        }
        return $this->nameLookup;
    }
}
