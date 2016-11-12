<?php

namespace GraphQL\Type\Builder;

class ObjectTypeConfig extends Config
{
    public function name($name)
    {
        $this->addConfig('name', $name, false);
        return $this;
    }

    public function description($description)
    {
        $this->addConfig('description', $description, false);
        return $this;
    }

    public function addField($name, $type, callable $resolve = null, $description = null, ArgsConfig $args = null, callable $complexity = null, $deprecationReason = null)
    {
        $field = [
            'name' => $name,
            'type' => $type,
            'description' => $description,
            'resolve' => $resolve,
            'complexity' => $complexity,
            'deprecationReason' => $deprecationReason,
            'args' => null === $args ? [] : $args->build(),
        ];

        $this->addConfig('fields', $field);

        return $this;
    }

    public function addInterface($interface)
    {
        $this->addConfig('interfaces', $interface);
        return $this;
    }

    public function isTypeOf(callable $isTypeOf = null)
    {
        $this->addConfig('isTypeOf', $isTypeOf, false);
        return $this;
    }

    public function resolveField(callable $resolveField = null)
    {
        $this->addConfig('resolveField', $resolveField, false);
        return $this;
    }
}
