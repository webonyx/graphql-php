<?php

namespace GraphQL\Language\AST;

class ListType extends Node implements Type
{
    protected $kind = NodeType::LIST_TYPE;

    /**
     * @var Node
     */
    protected $type;

    /**
     * ListType constructor.
     *
     * @param Node $type
     * @param null $loc
     */
    public function __construct(Node $type, $loc = null)
    {
        $this->type = $type;
        $this->loc = $loc;
    }

    /**
     * @return Node
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param Node $type
     *
     * @return ListType
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }
}
