<?php

namespace GraphQL\Type\Builder;

class ArgConfig extends Config
{
    use NameDescriptionConfigTrait;
    use TypeConfigTrait;
    
    /**
     * @param mixed $defaultValue
     *
     * @return $this
     */
    public function defaultValue($defaultValue)
    {
        $this->addConfig('defaultValue', $defaultValue, false);

        return $this;
    }
}
