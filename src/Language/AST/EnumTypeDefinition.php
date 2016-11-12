<?php

namespace GraphQL\Language\AST;

class EnumTypeDefinition extends Node implements TypeDefinition
{
    /**
     * @var string
     */
    protected $kind = NodeType::ENUM_TYPE_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var Directive[]
     */
    protected $directives;

    /**
     * @var EnumValueDefinition[]
     */
    protected $values;

    /**
     * EnumTypeDefinition constructor.
     *
     * @param Name                  $name
     * @param Directive[]           $directives
     * @param EnumValueDefinition[] $values
     * @param null                  $loc
     */
    public function __construct(Name $name, array $directives, array $values, $loc = null)
    {
        $this->name = $name;
        $this->directives = $directives;
        $this->values = $values;
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
     * @return EnumTypeDefinition
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
     * @return EnumTypeDefinition
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }

    /**
     * @return EnumValueDefinition[]
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param EnumValueDefinition[] $values
     *
     * @return EnumTypeDefinition
     */
    public function setValues($values)
    {
        $this->values = $values;

        return $this;
    }
}
