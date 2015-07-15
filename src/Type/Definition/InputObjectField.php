<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

class InputObjectField
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var callback|InputType
     */
    private $type;

    /**
     * @var mixed|null
     */
    public $defaultValue;

    /**
     * @var string|null
     */
    public $description;

    public function __construct(array $opts)
    {
        foreach ($opts as $k => $v) {
            $this->{$k} = $v;
        }
    }

    public function getType()
    {
        return Type::resolve($this->type);
    }
}
