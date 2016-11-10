<?php

namespace GraphQL\Language\AST;

class ListValue extends Node implements Value
{
    protected $kind = NodeType::LST;

    /**
     * @var array<Value>
     */
    protected $values;

    /**
     * ListValue constructor.
     *
     * @param array $values
     * @param null  $loc
     */
    public function __construct(array $values, $loc = null)
    {
        $this->values = $values;
        $this->loc = $loc;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param array $values
     *
     * @return ListValue
     */
    public function setValues($values)
    {
        $this->values = $values;

        return $this;
    }
}
