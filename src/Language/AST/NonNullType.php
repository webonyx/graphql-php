<?php

namespace GraphQL\Language\AST;

class NonNullType extends Node implements Type
{
    protected $kind = NodeType::NON_NULL_TYPE;

    /**
     * @var Name | ListType
     */
    protected $type;

    /**
     * NonNullType constructor.
     *
     * @param Name | ListType $type
     * @param null            $loc
     */
    public function __construct($type, $loc = null)
    {
        $this->type = $type;
        $this->loc = $loc;
    }

    /**
     * @return ListType|Name
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param ListType|Name $type
     *
     * @return NonNullType
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }
}
