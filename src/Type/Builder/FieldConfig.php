<?php

namespace GraphQL\Type\Builder;

class FieldConfig extends Config
{
    use NameDescriptionConfigTrait;
    use TypeConfigTrait;

    /**
     * @param callable|null $resolve
     *
     * @return $this
     */
    public function resolve(callable $resolve = null)
    {
        return $this->addConfig('resolve', $resolve, false);
    }

    /**
     * @param callable|null $complexity
     *
     * @return $this
     */
    public function complexity(callable $complexity = null)
    {
        return $this->addConfig('complexity', $complexity, false);
    }

    /**
     * @param string|null $deprecationReason
     * @return $this
     */
    public function deprecationReason($deprecationReason = null)
    {
        return $this->addConfig('deprecationReason', $deprecationReason, false);
    }

    /**
     * @param ArgConfig $arg
     *
     * @return $this
     */
    public function addArg(ArgConfig $arg)
    {
        return $this->addConfig('args', $arg->build());
    }

    /**
     * @param ArgsConfig $args
     *
     * @return $this
     */
    public function addArgs(ArgsConfig $args)
    {
        foreach ($args->build() as $arg) {
            $this->addConfig('args', $arg);
        }
        return $this;
    }
}
