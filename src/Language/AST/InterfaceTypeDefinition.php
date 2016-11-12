<?php

namespace GraphQL\Language\AST;

class InterfaceTypeDefinition extends Node implements TypeDefinition
{
    /**
     * @var string
     */
    protected $kind = NodeType::INTERFACE_TYPE_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var Directive[]
     */
    protected $directives;

    /**
     * @var FieldDefinition[]
     */
    protected $fields = [];

    /**
     * InterfaceTypeDefinition constructor.
     *
     * @param Name  $name
     * @param array $directives
     * @param array $fields
     * @param null  $loc
     */
    public function __construct(Name $name, array $directives, array $fields, $loc = null)
    {
        $this->name = $name;
        $this->directives = $directives;
        $this->fields = $fields;
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
     * @return InterfaceTypeDefinition
     */
    public function setName($name)
    {
        $this->name = $name;

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
     * @return InterfaceTypeDefinition
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }

    /**
     * @return FieldDefinition[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param FieldDefinition[] $fields
     *
     * @return InterfaceTypeDefinition
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }
}
