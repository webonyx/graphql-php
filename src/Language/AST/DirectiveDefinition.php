<?php

namespace GraphQL\Language\AST;

class DirectiveDefinition extends Node implements TypeSystemDefinition
{
    /**
     * @var string
     */
    protected $kind = NodeType::DIRECTIVE_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var Argument[]
     */
    protected $arguments;

    /**
     * @var Name[]
     */
    protected $locations;

    /**
     * DirectiveDefinition constructor.
     *
     * @param Name       $name
     * @param Argument[] $arguments
     * @param Name[]     $locations
     * @param null       $loc
     */
    public function __construct(Name $name, array $arguments, array $locations, $loc = null)
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->locations = $locations;
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
     * @return DirectiveDefinition
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
     * @return DirectiveDefinition
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * @return Name[]
     */
    public function getLocations()
    {
        return $this->locations;
    }

    /**
     * @param Name[] $locations
     *
     * @return DirectiveDefinition
     */
    public function setLocations($locations)
    {
        $this->locations = $locations;

        return $this;
    }
}
