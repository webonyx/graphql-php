<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\DefinitionContainer;

class BaseType implements DefinitionContainer
{
    /**
     * @var Type
     */
    protected $definition;

    /**
     * @return Type
     */
    public function getDefinition()
    {
        return $this->definition;
    }
}
