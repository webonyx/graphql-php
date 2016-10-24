<?php
namespace GraphQL\Examples\Blog\Type;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\DefinitionContainer;

abstract class BaseType implements DefinitionContainer
{
    // Base class to reduce boilerplate for those who prefer own clean hierarchy of classes
    // (and avoids extending GraphQL classes directly)

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
