<?php

namespace GraphQL\Language\AST;

class ObjectValue extends Node implements Value
{
    protected $kind = NodeType::OBJECT;

    /**
     * @var array<ObjectField>
     */
    protected $fields;

    /**
     * ObjectValue constructor.
     *
     * @param array $fields
     * @param null  $loc
     */
    public function __construct(array $fields, $loc = null)
    {
        $this->fields = $fields;
        $this->loc = $loc;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     *
     * @return ObjectValue
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }
}
