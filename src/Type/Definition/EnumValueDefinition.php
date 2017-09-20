<?php
namespace GraphQL\Type\Definition;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Utils\Utils;

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

    /**
     * @var EnumValueDefinitionNode|null
     */
    public $astNode;

    /**
     * @var array
     */
    public $config;

    public function __construct(array $config)
    {
        $this->name = isset($config['name']) ? $config['name'] : null;
        $this->value = isset($config['value']) ? $config['value'] : null;
        $this->deprecationReason = isset($config['deprecationReason']) ? $config['deprecationReason'] : null;
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->astNode = isset($config['astNode']) ? $config['astNode'] : null;

        $this->config = $config;
    }

    /**
     * @return bool
     */
    public function isDeprecated()
    {
        return !!$this->deprecationReason;
    }
}
