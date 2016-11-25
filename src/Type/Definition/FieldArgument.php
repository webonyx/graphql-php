<?php
namespace GraphQL\Type\Definition;


/**
 * Class FieldArgument
 *
 * @package GraphQL\Type\Definition
 * @todo Rename to ArgumentNode as it is also applicable to directives, not only fields
 */
class FieldArgument
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var mixed
     */
    public $defaultValue;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var array
     */
    public $config;

    /**
     * @var InputType|callable
     */
    private $type;

    /**
     * @var InputType
     */
    private $resolvedType;

    /**
     * @var bool
     */
    private $defaultValueExists = false;

    /**
     * @param array $config
     * @return array
     */
    public static function createMap(array $config)
    {
        $map = [];
        foreach ($config as $name => $argConfig) {
            if (!is_array($argConfig)) {
                $argConfig = ['type' => $argConfig];
            }
            $map[] = new self($argConfig + ['name' => $name]);
        }
        return $map;
    }

    /**
     * FieldArgument constructor.
     * @param array $def
     */
    public function __construct(array $def)
    {
        foreach ($def as $key => $value) {
            switch ($key) {
                case 'type':
                    $this->type = $value;
                    break;
                case 'name':
                    $this->name = $value;
                    break;
                case 'defaultValue':
                    $this->defaultValue = $value;
                    $this->defaultValueExists = true;
                    break;
                case 'description':
                    $this->description = $value;
                    break;
            }
        }
        $this->config = $def;
    }

    /**
     * @return InputType
     */
    public function getType()
    {
        if (null === $this->resolvedType) {
            $this->resolvedType = Type::resolve($this->type);
        }
        return $this->resolvedType;
    }

    /**
     * @return bool
     */
    public function defaultValueExists()
    {
        return $this->defaultValueExists;
    }
}
