<?php

namespace GraphQL\Language\AST;

class FragmentDefinition extends Node implements Definition, HasSelectionSet
{
    protected $kind = NodeType::FRAGMENT_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var NamedType
     */
    protected $typeCondition;

    /**
     * @var array<Directive>
     */
    protected $directives;

    /**
     * @var SelectionSet
     */
    protected $selectionSet;

    /**
     * FragmentDefinition constructor.
     *
     * @param Name         $name
     * @param NamedType    $typeCondition
     * @param array        $directives
     * @param SelectionSet $selectionSet
     * @param null         $loc
     */
    public function __construct(
        Name $name,
        NamedType $typeCondition,
        array $directives,
        SelectionSet $selectionSet,
        $loc = null
    )
    {
        $this->name = $name;
        $this->typeCondition = $typeCondition;
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
     * @return FragmentDefinition
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return NamedType
     */
    public function getTypeCondition()
    {
        return $this->typeCondition;
    }

    /**
     * @param NamedType $typeCondition
     *
     * @return FragmentDefinition
     */
    public function setTypeCondition($typeCondition)
    {
        $this->typeCondition = $typeCondition;

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
     * @return FragmentDefinition
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }

    /**
     * @return SelectionSet
     */
    public function getSelectionSet()
    {
        return $this->selectionSet;
    }

    /**
     * @param SelectionSet $selectionSet
     *
     * @return FragmentDefinition
     */
    public function setSelectionSet($selectionSet)
    {
        $this->selectionSet = $selectionSet;

        return $this;
    }
}
