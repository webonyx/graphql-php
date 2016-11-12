<?php

namespace GraphQL\Language\AST;

class InputValueDefinition extends Node
{
    /**
     * @var string
     */
    protected $kind = NodeType::INPUT_VALUE_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var Type
     */
    protected $type;

    /**
     * @var Value
     */
    protected $defaultValue;

    /**
     * @var Directive[]
     */
    protected $directives;

    /**
     * InputValueDefinition constructor.
     *
     * @param Name  $name
     * @param Type  $type
     * @param Value $defaultValue
     * @param array $directives
     * @param null  $loc
     */
    public function __construct(Name $name, Type $type, Value $defaultValue = null, array $directives, $loc = null)
    {
        $this->name = $name;
        $this->type = $type;
        $this->defaultValue = $defaultValue;
        $this->directives = $directives;
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
     * @return InputValueDefinition
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param Type $type
     *
     * @return InputValueDefinition
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Value
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @param Value $defaultValue
     *
     * @return InputValueDefinition
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    /**
     * @return Directive[]
     */
    public function getDirectives()
    {
        return $this->directives;
    }

    /**
     * @param Directive[] $directives
     *
     * @return InputValueDefinition
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }
}
