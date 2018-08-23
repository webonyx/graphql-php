<?php
namespace GraphQL\Type\Definition;
use GraphQL\Language\AST\InputValueDefinitionNode;

/**
 * Class InputObjectField
 * @package GraphQL\Type\Definition
 */
class InputObjectField
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var mixed|null
     */
    public $defaultValue;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var callable|InputType
     */
    public $type;

    /**
     * @var InputValueDefinitionNode|null
     */
    public $astNode;

    /**
     * @var array
     */
    public $config;

    /**
     * Helps to differentiate when `defaultValue` is `null` and when it was not even set initially
     *
     * @var bool
     */
    private $defaultValueExists = false;

    /**
     * InputObjectField constructor.
     * @param array $opts
     */
    public function __construct(array $opts)
    {
        foreach ($opts as $k => $v) {
            switch ($k) {
                case 'defaultValue':
                    $this->defaultValue = $v;
                    $this->defaultValueExists = true;
                    break;
                case 'defaultValueExists':
                    break;
                default:
                    $this->{$k} = $v;
            }
        }
        $this->config = $opts;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function defaultValueExists()
    {
        return $this->defaultValueExists;
    }
}
