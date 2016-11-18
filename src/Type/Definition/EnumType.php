<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Utils;

/**
 * Class EnumType
 * @package GraphQL\Type\Definition
 */
class EnumType extends Type implements InputType, OutputType, LeafType
{
    /**
     * @var EnumValueDefinition[]
     */
    private $values;

    /**
     * @var Utils\MixedStore<mixed, EnumValueDefinition>
     */
    private $valueLookup;

    /**
     * @var \ArrayObject<string, EnumValueDefinition>
     */
    private $nameLookup;

    public function __construct($config)
    {
        if (!isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

        Config::validate($config, [
            'name' => Config::NAME | Config::REQUIRED,
            'values' => Config::arrayOf([
                'name' => Config::NAME | Config::REQUIRED,
                'value' => Config::ANY,
                'deprecationReason' => Config::STRING,
                'description' => Config::STRING
            ], Config::KEY_AS_NAME | Config::MAYBE_NAME),
            'description' => Config::STRING
        ]);

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->values = [];

        if (!empty($config['values'])) {
            foreach ($config['values'] as $name => $value) {
                if (!is_array($value)) {
                    if (is_string($name)) {
                        $value = ['name' => $name, 'value' => $value];
                    } else if (is_int($name) && is_string($value)) {
                        $value = ['name' => $value, 'value' => $value];
                    }
                }
                // value will be equal to name only if 'value'  is not set in definition
                $this->values[] = new EnumValueDefinition($value + ['name' => $name, 'value' => $name]);
            }
        }
    }

    /**
     * @return EnumValueDefinition[]
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
        if ($value instanceof EnumValueNode) {
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
     * @return Utils\MixedStore<mixed, EnumValueDefinition>
     */
    private function getValueLookup()
    {
        if (null === $this->valueLookup) {
            $this->valueLookup = new Utils\MixedStore();

            foreach ($this->getValues() as $valueName => $value) {
                $this->valueLookup->offsetSet($value->value, $value);
            }
        }

        return $this->valueLookup;
    }

    /**
     * @return \ArrayObject<string, EnumValueDefinition>
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
