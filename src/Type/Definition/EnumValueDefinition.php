<?php
namespace GraphQL\Type\Definition;

class EnumValueDefinition
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var mixed
     */
    public $value;

    /**
     * @var string|null
     */
    public $deprecationReason;

    /**
     * @var string|null
     */
    public $description;
}
