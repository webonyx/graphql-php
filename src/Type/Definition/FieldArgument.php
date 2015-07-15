<?php
namespace GraphQL\Type\Definition;


use GraphQL\Utils;

class FieldArgument
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var InputType
     */
    private $type;

    private $resolvedType;

    /**
     * @var mixed
     */
    public $defaultValue;

    /**
     * @var string|null
     */
    public $description;

    public static function createMap(array $config)
    {
        $map = [];
        foreach ($config as $name => $argConfig) {
            $map[] = new self($argConfig + ['name' => $name]);
        }
        return $map;
    }

    public function __construct(array $def)
    {
        foreach ($def as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function getType()
    {
        if (null === $this->resolvedType) {
            $this->resolvedType = Type::resolve($this->type);
        }
        return $this->resolvedType;
    }
}
