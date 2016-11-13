<?php

namespace GraphQL\Type\Builder;

/**
 * Class NameDescriptionConfigTrait
 *
 * @method $this addConfig(string $name, mixed $value, boolean $append = true)
 */
trait NameDescriptionConfigTrait
{
    /**
     * @param string $name
     *
     * @return $this
     */
    public function name($name)
    {
        $this->addConfig('name', $name, false);

        return $this;
    }

    /**
     * @param string|null $description
     *
     * @return $this
     */
    public function description($description)
    {
        $this->addConfig('description', $description, false);

        return $this;
    }
}
