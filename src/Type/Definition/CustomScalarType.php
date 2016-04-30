<?php
namespace GraphQL\Type\Definition;


class CustomScalarType extends ScalarType
{
    private $config;

    function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->config = $config;
        parent::__construct();
    }

    public function serialize($value)
    {
        return call_user_func($this->config['serialize'], $value);
    }

    public function parseValue($value)
    {
        return call_user_func($this->config['parseValue'], $value);
    }

    public function parseLiteral(/* GraphQL\Language\AST\Value */ $valueAST)
    {
        return call_user_func($this->config['parseLiteral'], $valueAST);
    }
}
