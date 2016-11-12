<?php

namespace GraphQL\Language\AST;

class FieldDefinition extends Node
{
    /**
     * @var string
     */
    protected $kind = NodeType::FIELD_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var InputValueDefinition[]
     */
    protected $arguments;

    /**
     * @var Type
     */
    protected $type;

    /**
     * @var Directive[]
     */
    protected $directives;

    /**
     * FieldDefinition constructor.
     *
     * @param Name                   $name
     * @param InputValueDefinition[] $arguments
     * @param Type                   $type
     * @param Directive[]            $directives
     * @param null                   $loc
     */
    public function __construct(Name $name, array $arguments, Type $type, array $directives, $loc = null)
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->type = $type;
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
     * @return FieldDefinition
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return InputValueDefinition[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param InputValueDefinition[] $arguments
     *
     * @return FieldDefinition
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;

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
     * @return FieldDefinition
     */
    public function setType($type)
    {
        $this->type = $type;

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
     * @return FieldDefinition
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }
}
