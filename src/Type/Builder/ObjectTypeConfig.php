<?php

namespace GraphQL\Type\Builder;

use GraphQL\Type\Definition\InterfaceType;

class ObjectTypeConfig extends Config
{
    use NameDescriptionConfigTrait;
    use FieldsConfigTrait;

    /**
     * @param callable $interfaces
     *
     * @return $this
     */
    public function interfaces(callable $interfaces)
    {
        $this->addConfig('interfaces', $interfaces, false);

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
        } else {
            $this->addConfig('fields', $fields, false);
        }
        return $this;
    }

    /**
     * @param InterfaceType|callable $interface
     *
     * @return $this
     */
    public function addInterface($interface)
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
