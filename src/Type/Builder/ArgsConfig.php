<?php

namespace GraphQL\Type\Builder;

class ArgsConfig extends Config
{
    public function addArgConfig(ArgConfig $argConfig)
    {
        return $this->addConfig('args', $argConfig->build());
    }

    public function addArg($name, $type, $defaultValue = null, $description = null)
    {
        $argConfig = ArgConfig::create()
            ->name($name)
            ->type($type)
            ->defaultValue($defaultValue)
            ->description($description);

        return $this->addArgConfig($argConfig);
    }

    public function build()
    {
        $args = parent::build();

        return isset($args['args']) ? $args['args'] : [];
    }
}
