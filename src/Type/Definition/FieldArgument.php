<?php
namespace GraphQL\Type\Definition;


/**
 * Class FieldArgument
 *
 * @package GraphQL\Type\Definition
 * @todo Rename to Argument as it is also applicable to directives, not only fields
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
        $def += [
            'type' => null,
            'name' => null,
            'defaultValue' => null,
            'description' => null
        ];

        $this->type = $def['type'];
        $this->name = $def['name'];
        $this->description = $def['description'];
        $this->defaultValue = $def['defaultValue'];
        $this->config = $def;
    }

    /**
     * @return InputType
     * @deprecated in favor of setting 'fields' as closure per objectType vs on individual field/argument level
     */
    public function getType()
    {
        if (null === $this->resolvedType) {
            $this->resolvedType = Type::resolve($this->type);
        }
        return $this->resolvedType;
    }
}
