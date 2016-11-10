<?php

namespace GraphQL\Language\AST;

class UnionTypeDefinition extends Node implements TypeDefinition
{
    /**
     * @var string
     */
    protected $kind = NodeType::UNION_TYPE_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var Directive[]
     */
    protected $directives;

    /**
     * @var NamedType[]
     */
    protected $types = [];

    /**
     * UnionTypeDefinition constructor.
     *
     * @param Name  $name
     * @param array $directives
     * @param array $types
     * @param null  $loc
     */
    public function __construct(
        Name $name,
        array $directives,
        array $types,
        $loc = null
    )
    {
        $this->name = $name;
        $this->directives = $directives;
        $this->types = $types;
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
     * @return UnionTypeDefinition
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
     * @return UnionTypeDefinition
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }

    /**
     * @return NamedType[]
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param NamedType[] $types
     *
     * @return UnionTypeDefinition
     */
    public function setTypes($types)
    {
        $this->types = $types;

        return $this;
    }
}
