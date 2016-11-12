<?php

namespace GraphQL\Language\AST;

class VariableDefinition extends Node implements Definition
{
    protected $kind = NodeType::VARIABLE_DEFINITION;

    /**
     * @var Variable
     */
    protected $variable;

    /**
     * @var Type
     */
    protected $type;

    /**
     * @var Value|null
     */
    protected $defaultValue;

    /**
     * VariableDefinition constructor.
     *
     * @param Variable $variable
     * @param Type     $type
     * @param Value    $defaultValue
     * @param null     $loc
     */
    public function __construct(Variable $variable, Type $type, Value $defaultValue = null, $loc = null)
    {
        $this->variable = $variable;
        $this->type = $type;
        $this->defaultValue = $defaultValue;
        $this->loc = $loc;
    }

    /**
     * @return Variable
     */
    public function getVariable()
    {
        return $this->variable;
    }

    /**
     * @param Variable $variable
     *
     * @return VariableDefinition
     */
    public function setVariable($variable)
    {
        $this->variable = $variable;

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
     * @return VariableDefinition
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Value|null
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @param Value|null $defaultValue
     *
     * @return VariableDefinition
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }
}
