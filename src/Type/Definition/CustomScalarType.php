<?php
namespace GraphQL\Type\Definition;

/**
 * Class CustomScalarType
 * @package GraphQL\Type\Definition
 */
class CustomScalarType extends ScalarType
{
    /**
     * @var array
     */
    public $config;

    /**
     * CustomScalarType constructor.
     * @param array $config
     */
    function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->config = $config;
        parent::__construct();
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function serialize($value)
    {
        return call_user_func($this->config['serialize'], $value);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function parseValue($value)
    {
        return call_user_func($this->config['parseValue'], $value);
    }

    /**
     * @param $valueNode
     * @return mixed
     */
    public function parseLiteral(/* GraphQL\Language\AST\ValueNode */ $valueNode)
    {
        return call_user_func($this->config['parseLiteral'], $valueNode);
    }
}
