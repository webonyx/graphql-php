<?php

namespace GraphQL\Language\AST;

class Directive extends Node
{
    protected $kind = NodeType::DIRECTIVE;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Argument[]
     */
    public $arguments;

    /**
     * @param array $value
     * @param null  $loc
     */
    public function __construct($name, $arguments, $loc = null)
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->loc = $loc;
    }

    /**
     * @return Name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param Name $name
     *
     * @return Directive
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Argument[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param Argument[] $arguments
     *
     * @return Directive
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }
}
