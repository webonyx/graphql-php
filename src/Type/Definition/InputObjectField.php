<?php
namespace GraphQL\Type\Definition;

/**
 * Class InputObjectField
 * @package GraphQL\Type\Definition
 */
class InputObjectField
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var mixed|null
     */
    public $defaultValue;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var callback|InputType
     */
    public $type;

    /**
     * InputObjectField constructor.
     * @param array $opts
     */
    public function __construct(array $opts)
    {
        foreach ($opts as $k => $v) {
            $this->{$k} = $v;
        }
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return Type::resolve($this->type);
    }
}
