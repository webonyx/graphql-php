<?php

namespace GraphQL\Type\Builder;

use GraphQL\Type\Definition\InterfaceType;

class ObjectTypeConfig extends Config
{
    use NameDescriptionConfigTrait;
    use FieldsConfigTrait;

    /**
     * @param InterfaceType[] $interfaces
     *
     * @return $this
     */
    public function interfaces(array $interfaces)
    {
        $this->addConfig('interfaces', [], false);
        foreach ($interfaces as $interface) {
            $this->addInterface($interface);
        }

        return $this;
    }

    /**
     * @param callable|FieldsConfig $fields
     *
     * @return $this
     */
    public function fields($fields)
    {
        if ($fields instanceof FieldsConfig) {
            $this->addConfig('fields', $fields->build(), false);
        } elseif ($fields) {
            $this->addConfig('fields', $fields, false);
        }

        return $this;
    }

    /**
     * @param InterfaceType $interface
     *
     * @return $this
     */
    public function addInterface(InterfaceType $interface)
    {
        $this->addConfig('interfaces', $interface);

        return $this;
    }

    /**
     * @param callable|null $isTypeOf
     *
     * @return $this
     */
    public function isTypeOf(callable $isTypeOf = null)
    {
        $this->addConfig('isTypeOf', $isTypeOf, false);

        return $this;
    }

    /**
     * @param callable|null $resolveField
     *
     * @return $this
     */
    public function resolveField(callable $resolveField = null)
    {
        $this->addConfig('resolveField', $resolveField, false);

        return $this;
    }
}
