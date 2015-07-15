<?php
namespace GraphQL\Type\Definition;


class ScalarTypeConfig
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var \Closure
     */
    public $coerce;

    /**
     * @var \Closure
     */
    public $coerceLiteral;
}
