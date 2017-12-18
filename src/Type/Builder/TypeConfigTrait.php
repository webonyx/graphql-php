<?php

namespace GraphQL\Type\Builder;

use GraphQL\Type\Definition\Type;

/**
 * Class TypeConfigTrait
 *
 * @method $this addConfig(string $name, mixed $value, boolean $append = true)
 */
trait TypeConfigTrait
{
    /**
     * @param Type|callable $type
     *
     * @return $this
     */
    public function type($type)
    {
        $this->addConfig('type', $type, false);

        return $this;
    }
}
