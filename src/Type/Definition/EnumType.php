<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Utils\MixedStore;
use GraphQL\Utils\Utils;

/**
 * Class EnumType
 * @package GraphQL\Type\Definition
 */
class EnumType extends Type implements InputType, OutputType, LeafType, NamedType
{
    /**
     * @var EnumTypeDefinitionNode|null
     */
    public $astNode;

    /**
     * @var EnumValueDefinition[]
     */
    private $values;

    /**
     * @var MixedStore<mixed, EnumValueDefinition>
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

        Utils::invariant(is_string($config['name']), 'Must provide name.');

        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->astNode = isset($config['astNode']) ? $config['astNode'] : null;
        $this->config = $config;
    }

    /**
     * @return EnumValueDefinition[]
     */
    public function getValues()
    {
        if ($this->values === null) {
            $this->values = [];
            $config = $this->config;

            if (isset($config['values'])) {
                if (!is_array($config['values'])) {
                    throw new InvariantViolation("{$this->name} values must be an array");
                }
                foreach ($config['values'] as $name => $value) {
                    if (is_string($name)) {
                        if (!is_array($value)) {
                            $value = ['name' => $name, 'value' => $value];
                        } else {
                            $value += ['name' => $name, 'value' => $name];
                        }
                    } else if (is_int($name) && is_string($value)) {
                        $value = ['name' => $value, 'value' => $value];
                    } else {
                        throw new InvariantViolation("{$this->name} values must be an array with value names as keys.");
                    }
                    $this->values[] = new EnumValueDefinition($value);
                }
            }
        }

        return $this->values;
    }

    /**
     * @param $name
     * @return EnumValueDefinition|null
     */
    public function getValue($name)
    {
        $lookup = $this->getNameLookup();
        return is_scalar($name) && isset($lookup[$name]) ? $lookup[$name] : null;
    }

    /**
     * @param $value
     * @return mixed
     * @throws Error
     */
    public function serialize($value)
    {
        $lookup = $this->getValueLookup();
        if (isset($lookup[$value])) {
            return $lookup[$value]->name;
        }

        throw new Error("Cannot serialize value as enum: " . Utils::printSafe($value));
    }

    /**
     * @param $value
     * @return mixed
     * @throws Error
     */
    public function parseValue($value)
    {
        $lookup = $this->getNameLookup();
        if (isset($lookup[$value])) {
            return $lookup[$value]->value;
        }

        throw new Error("Cannot represent value as enum: " . Utils::printSafe($value));
    }

    /**
     * @param $valueNode
     * @param array|null $variables
     * @return null
     * @throws \Exception
     */
    public function parseLiteral($valueNode, array $variables = null)
    {
        if ($valueNode instanceof EnumValueNode) {
            $lookup = $this->getNameLookup();
            if (isset($lookup[$valueNode->value])) {
                $enumValue = $lookup[$valueNode->value];
                if ($enumValue) {
                    return $enumValue->value;
                }
            }
        }

        // Intentionally without message, as all information already in wrapped Exception
        throw new \Exception();
    }

    /**
     * @return MixedStore<mixed, EnumValueDefinition>
     */
    private function getValueLookup()
    {
        if (null === $this->valueLookup) {
            $this->valueLookup = new MixedStore();

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

    /**
     * @throws InvariantViolation
     */
    public function assertValid()
    {
        parent::assertValid();

        Utils::invariant(
            isset($this->config['values']),
            "{$this->name} values must be an array."
        );

        $values = $this->getValues();
        foreach ($values as $value) {
            Utils::invariant(
                !isset($value->config['isDeprecated']),
                "{$this->name}.{$value->name} should provide \"deprecationReason\" instead of \"isDeprecated\"."
            );
        }
    }
}
