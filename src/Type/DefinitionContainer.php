<?php
namespace GraphQL\Type;
use GraphQL\Type\Definition\Type;

/**
 * Interface DefinitionContainer
 * @package GraphQL\Type
 */
interface DefinitionContainer
{
    /**
     * @return Type
     */
    public function getDefinition();
}
