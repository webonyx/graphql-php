<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Utils\MixedStore;
use GraphQL\Utils\Utils;

/**
 * Class EnumType
 * @package GraphQL\Type\Definition
 */
class EnumType extends Type implements InputType, OutputType, LeafType
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

        Utils::assertValidName($config['name'], !empty($config['isIntrospection']));

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
     * @return null
     */
    public function serialize($value)
    {
        $lookup = $this->getValueLookup();
        return isset($lookup[$value]) ? $lookup[$value]->name : null;
    }

    /**
     * @param string $value
     * @return bool
     */
    public function isValidValue($value)
    {
        return is_string($value) && $this->getNameLookup()->offsetExists($value);
    }

    /**
     * @param $valueNode
     * @return bool
     */
    public function isValidLiteral($valueNode)
    {
        return $valueNode instanceof EnumValueNode && $this->getNameLookup()->offsetExists($valueNode->value);
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

        Utils::invariant(
            !empty($values),
            "{$this->name} values must be not empty."
        );
        foreach ($values as $value) {
            try {
                Utils::assertValidName($value->name);
            } catch (InvariantViolation $e) {
                throw new InvariantViolation(
                    "{$this->name} has value with invalid name: " .
                    Utils::printSafe($value->name) . " ({$e->getMessage()})"
                );
            }
            Utils::invariant(
                !in_array($value->name, ['true', 'false', 'null']),
                "{$this->name}: \"{$value->name}\" can not be used as an Enum value."
            );
            Utils::invariant(
                !isset($value->config['isDeprecated']),
                "{$this->name}.{$value->name} should provide \"deprecationReason\" instead of \"isDeprecated\"."
            );
        }
    }
}
