<?php

namespace GraphQL\Type\Builder;

/**
 * Class FieldsConfigTrait
 *
 * @method $this addConfig(string $name, mixed $value, boolean $append = true)
 */
trait FieldsConfigTrait
{
    public function addFieldConfig(FieldConfig $fieldConfig)
    {
        return $this->addConfig('fields', $fieldConfig->build());
    }

    public function addField($name, $type, $description = null, ArgsConfig $args = null, callable $resolve = null, callable $complexity = null)
    {
        return $this->pushField($name, $type, $description, $args, $resolve, $complexity);
    }

    public function addDeprecatedField($name, $type, $deprecationReason, $description = null, ArgsConfig $args = null, callable $resolve = null, callable $complexity = null)
    {
        return $this->pushField($name, $type, $description, $args, $resolve, $complexity, $deprecationReason);
    }

    protected function pushField($name, $type, $description = null, ArgsConfig $args = null, callable $resolve = null, callable $complexity = null, $deprecationReason = null)
    {
        $fieldConfig = FieldConfig::create()
            ->name($name)
            ->type($type)
            ->resolve($resolve)
            ->description($description);

        if (null !== $args) {
            $fieldConfig->addArgs($args);
        }
        $fieldConfig->complexity($complexity);
        if (null !== $deprecationReason) {
            $fieldConfig->deprecationReason($deprecationReason);
        }

        return $this->addFieldConfig($fieldConfig);
    }
}
