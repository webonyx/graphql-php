<?php

namespace GraphQL\Language\AST;


class ObjectField extends Node
{
    protected $kind = NodeType::OBJECT_FIELD;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var Value
     */
    protected $value;

    /**
     * ObjectField constructor.
     *
     * @param Name  $name
     * @param Value $value
     * @param null  $loc
     */
    public function __construct(Name $name, Value $value, $loc = null)
    {
        $this->name = $name;
        $this->value = $value;
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
     * @return ObjectField
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param Value $value
     *
     * @return ObjectField
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }
}
