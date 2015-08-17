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
     * source?: any,
     * args?: ?{[argName: string]: any},
     * context?: any,
     * fieldAST?: any,
     * fieldType?: any,
     * parentType?: any,
     * schema?: GraphQLSchema
     *
     * @var callable
     */
    public $resolve;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var string|null
     */
    public $deprecationReason;

    private static $def;

    public static function getDefinition()
    {
        return self::$def ?: (self::$def = [
            'name' => Config::STRING | Config::REQUIRED,
            'type' => Config::OUTPUT_TYPE | Config::REQUIRED,
            'args' => Config::arrayOf([
                'name' => Config::STRING | Config::REQUIRED,
                'type' => Config::INPUT_TYPE | Config::REQUIRED,
                'defaultValue' => Config::ANY
            ], Config::KEY_AS_NAME),
            'resolve' => Config::CALLBACK,
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
        $this->resolve = isset($config['resolve']) ? $config['resolve'] : null;
        $this->args = isset($config['args']) ? FieldArgument::createMap($config['args']) : [];

        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->deprecationReason = isset($config['deprecationReason']) ? $config['deprecationReason'] : null;
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
