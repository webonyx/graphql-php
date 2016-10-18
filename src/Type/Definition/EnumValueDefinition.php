<?php
namespace GraphQL\Type\Definition;
use GraphQL\Utils;

/**
 * Class EnumValueDefinition
 * @package GraphQL\Type\Definition
 */
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

    public function __construct(array $config)
    {
        Utils::assign($this, $config);
    }

    /**
     * @return bool
     */
    public function isDeprecated()
    {
        return !!$this->deprecationReason;
    }
}
