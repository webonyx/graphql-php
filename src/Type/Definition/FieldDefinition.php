<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

class FieldDefinition
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var OutputType
     */
    private $type;

    private $resolvedType;

    /**
     * @var array<GraphQLFieldArgument>
     */
    public $args;

    /**
     * Callback for resolving field value given parent value.
     * Mutually exclusive with `map`
     *
     * @var callable
     */
    public $resolveFn;

    /**
     * Callback for mapping list of parent values to list of field values.
     * Mutually exclusive with `resolve`
     *
     * @var callable
     */
    public $mapFn;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var string|null
     */
    public $deprecationReason;

    /**
     * Original field definition config
     *
     * @var array
     */
    public $config;

    private static $def;

    public static function getDefinition()
    {
        return self::$def ?: (self::$def = [
            'name' => Config::STRING | Config::REQUIRED,
            'type' => Config::OUTPUT_TYPE | Config::REQUIRED,
            'args' => Config::arrayOf([
                'name' => Config::STRING | Config::REQUIRED,
                'type' => Config::INPUT_TYPE | Config::REQUIRED,
                'description' => Config::STRING,
                'defaultValue' => Config::ANY
            ], Config::KEY_AS_NAME),
            'resolve' => Config::CALLBACK,
            'map' => Config::CALLBACK,
            'description' => Config::STRING,
            'deprecationReason' => Config::STRING,
        ]);
    }

    /**
     * @param array|Config $fields
     * @return array
     */
    public static function createMap(array $fields)
    {
        $map = [];
        foreach ($fields as $name => $field) {
            if (!isset($field['name'])) {
                $field['name'] = $name;
            }
            $map[$name] = self::create($field);
        }
        return $map;
    }

    /**
     * @param array|Config $field
     * @return FieldDefinition
     */
    public static function create($field)
    {
        Config::validate($field, self::getDefinition());
        return new self($field);
    }

    protected function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->type = $config['type'];
        $this->resolveFn = isset($config['resolve']) ? $config['resolve'] : null;
        $this->mapFn = isset($config['map']) ? $config['map'] : null;
        $this->args = isset($config['args']) ? FieldArgument::createMap($config['args']) : [];

        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->deprecationReason = isset($config['deprecationReason']) ? $config['deprecationReason'] : null;

        $this->config = $config;
    }

    /**
     * @param $name
     * @return FieldArgument|null
     */
    public function getArg($name)
    {
        foreach ($this->args ?: [] as $arg) {
            /** @var FieldArgument $arg */
            if ($arg->name === $name) {
                return $arg;
            }
        }
        return null;
    }

    /**
     * @return Type
     */
    public function getType()
    {
        if (null === $this->resolvedType) {
            // TODO: deprecate types as callbacks - instead just allow field definitions to be callbacks
            $this->resolvedType = Type::resolve($this->type);
        }
        return $this->resolvedType;
    }
}
