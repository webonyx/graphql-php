<?php

namespace GraphQL\Language\AST;

class ObjectTypeDefinition extends Node implements TypeDefinition
{
    /**
     * @var string
     */
    protected $kind = NodeType::OBJECT_TYPE_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var NamedType[]
     */
    protected $interfaces = [];

    /**
     * @var Directive[]
     */
    protected $directives;

    /**
     * @var FieldDefinition[]
     */
    protected $fields;

    /**
     * ObjectTypeDefinition constructor.
     *
     * @param Name  $name
     * @param array $interfaces
     * @param array $directives
     * @param array $fields
     * @param null  $loc
     */
    public function __construct(Name $name, array $interfaces, array $directives, array $fields, $loc = null)
    {
        $this->name = $name;
        $this->interfaces = $interfaces;
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
     * @return ObjectTypeDefinition
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return NamedType[]
     */
    public function getInterfaces()
    {
        return $this->interfaces;
    }

    /**
     * @param NamedType[] $interfaces
     *
     * @return ObjectTypeDefinition
     */
    public function setInterfaces($interfaces)
    {
        $this->interfaces = $interfaces;

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
     * @return ObjectTypeDefinition
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
     * @return ObjectTypeDefinition
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }
}
