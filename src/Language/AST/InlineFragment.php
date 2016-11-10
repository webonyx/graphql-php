<?php

namespace GraphQL\Language\AST;

class InlineFragment extends Node implements Selection
{
    protected $kind = NodeType::INLINE_FRAGMENT;

    /**
     * @var NamedType
     */
    protected $typeCondition;

    /**
     * @var array<Directive>|null
     */
    protected $directives;

    /**
     * @var SelectionSet
     */
    protected $selectionSet;

    /**
     * InlineFragment constructor.
     *
     * @param NamedType    $typeCondition
     * @param array        $directives
     * @param SelectionSet $selectionSet
     * @param null         $loc
     */
    public function __construct(
        NamedType $typeCondition = null,
        array $directives,
        SelectionSet $selectionSet,
        $loc = null
    )
    {
        $this->typeCondition = $typeCondition;
        $this->directives = $directives;
        $this->selectionSet = $selectionSet;
        $this->loc = $loc;
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
     * @return InlineFragment
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
     * @return InlineFragment
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
     * @return InlineFragment
     */
    public function setSelectionSet($selectionSet)
    {
        $this->selectionSet = $selectionSet;

        return $this;
    }
}
