<?php

namespace GraphQL\Language\AST;

class SelectionSet extends Node
{
    protected $kind = NodeType::SELECTION_SET;

    /**
     * @var array<Selection>
     */
    protected $selections;

    /**
     * SelectionSet constructor.
     *
     * @param array $selections
     * @param null  $loc
     */
    public function __construct(array $selections = [], $loc = null)
    {
        $this->selections = $selections;
        $this->loc = $loc;
    }

    /**
     * @return array
     */
    public function getSelections()
    {
        return $this->selections;
    }

    /**
     * @param array $selections
     *
     * @return SelectionSet
     */
    public function setSelections($selections)
    {
        $this->selections = $selections;

        return $this;
    }
}
