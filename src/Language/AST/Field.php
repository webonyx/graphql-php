<?php

namespace GraphQL\Language\AST;

class Field extends Node implements Selection
{
    protected $kind = NodeType::FIELD;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var Name|null
     */
    protected $alias;

    /**
     * @var array<Argument>|null
     */
    protected $arguments;

    /**
     * @var array<Directive>|null
     */
    protected $directives;

    /**
     * @var SelectionSet|null
     */
    protected $selectionSet;

    /**
     * Field constructor.
     *
     * @param Name         $name
     * @param Name         $alias
     * @param array        $arguments
     * @param array        $directives
     * @param SelectionSet $selectionSet
     * @param null         $loc
     */
    public function __construct(
        Name $name = null,
        Name $alias = null,
        array $arguments = [],
        array $directives = [],
        SelectionSet $selectionSet = null,
        $loc = null
    )
    {
        $this->name = $name;
        $this->alias = $alias;
        $this->arguments = $arguments;
        $this->directives = $directives;
        $this->selectionSet = $selectionSet;
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
     * @return Field
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Name|null
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param Name|null $alias
     *
     * @return Field
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param array $arguments
     *
     * @return Field
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }
    
    /**
     * @return array
     */
    public function getDirectives()
    {
        return $this->directives;
    }

    /**
     * @param array $directives
     *
     * @return Field
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }

    /**
     * @return SelectionSet|null
     */
    public function getSelectionSet()
    {
        return $this->selectionSet;
    }

    /**
     * @param SelectionSet|null $selectionSet
     *
     * @return Field
     */
    public function setSelectionSet($selectionSet)
    {
        $this->selectionSet = $selectionSet;

        return $this;
    }
}
