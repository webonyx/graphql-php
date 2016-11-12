<?php

namespace GraphQL\Type\Builder;

class ArgsConfig extends Config
{
    public function addArg($name, $type, $defaultValue = null, $description = null)
    {
        $this->addConfig('args', [
            'name' => $name,
            'type' => $type,
            'defaultValue' => $defaultValue,
            'description' => $description,
        ]);

        return $this;
    }

    public function build()
    {
        $args = parent::build();

        return isset($args['args']) ? $args['args'] : [];
    }
}
