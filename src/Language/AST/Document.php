<?php

namespace GraphQL\Language\AST;

class Document extends Node
{
    protected $kind = NodeType::DOCUMENT;

    /**
     * @var Definition[]
     */
    protected $definitions;

    /**
     * Document constructor.
     *
     * @param Definition[] $definitions
     * @param null         $loc
     */
    public function __construct(array $definitions, $loc = null)
    {
        $this->definitions = $definitions;
        $this->loc = $loc;
    }

    /**
     * @return Definition[]
     */
    public function getDefinitions()
    {
        return $this->definitions;
    }

    /**
     * @param Definition[] $definitions
     *
     * @return Document
     */
    public function setDefinitions($definitions)
    {
        $this->definitions = $definitions;

        return $this;
    }
}
