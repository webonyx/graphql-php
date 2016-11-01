<?php
namespace GraphQL\Type\Definition;
use GraphQL\Error\InvariantViolation;
use GraphQL\Utils;

/**
 * Class FieldDefinition
 * @package GraphQL\Type\Definition
 * @todo Move complexity-related code to it's own place
 */
class FieldDefinition
{
    const DEFAULT_COMPLEXITY_FN = 'GraphQL\Type\Definition\FieldDefinition::defaultComplexity';

    /**
     * @var string
     */
    public $name;

    /**
     * @var FieldArgument[]
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

    /**
     * @var OutputType|callable
     */
    private $type;

    /**
     * @var OutputType
     */
    private $resolvedType;

    private static $def;

    /**
     * @return array
     */
    public static function getDefinition()
    {
        return self::$def ?: (self::$def = [
            'name' => Config::NAME | Config::REQUIRED,
            'type' => Config::OUTPUT_TYPE | Config::REQUIRED,
            'args' => Config::arrayOf([
                'name' => Config::NAME | Config::REQUIRED,
                'type' => Config::INPUT_TYPE | Config::REQUIRED,
                'description' => Config::STRING,
                'defaultValue' => Config::ANY
            ], Config::KEY_AS_NAME | Config::MAYBE_TYPE),
            'resolve' => Config::CALLBACK,
            'map' => Config::CALLBACK,
            'description' => Config::STRING,
            'deprecationReason' => Config::STRING,
            'complexity' => Config::CALLBACK,
        ]);
    }

    /**
     * @param array|Config $fields
     * @param string $parentTypeName
     * @return array
     */
    public static function createMap(array $fields, $parentTypeName = null)
    {
        $map = [];
        foreach ($fields as $name => $field) {
            if (is_array($field)) {
                if (!isset($field['name']) && is_string($name)) {
                    $field['name'] = $name;
                }
                $fieldDef = self::create($field, $parentTypeName);
            } else if ($field instanceof FieldDefinition) {
                $fieldDef = $field;
            } else {
                if (is_string($name)) {
                    $fieldDef = self::create(['name' => $name, 'type' => $field], $parentTypeName);
                } else {
                    throw new InvariantViolation(
                        "Unexpected field definition for type $parentTypeName at key $name: " . Utils::printSafe($field)
                    );
                }
            }
            $map[$fieldDef->name] = $fieldDef;
        }
        return $map;
    }

    /**
     * @param array|Config $field
     * @param string $typeName
     * @return FieldDefinition
     */
    public static function create($field, $typeName = null)
    {
        if ($typeName) {
            Config::validateField($typeName, $field, self::getDefinition());
        }
        return new self($field);
    }

    /**
     * FieldDefinition constructor.
     * @param array $config
     */
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

        $this->complexityFn = isset($config['complexity']) ? $config['complexity'] : static::DEFAULT_COMPLEXITY_FN;
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

    /**
     * @return bool
     */
    public function isDeprecated()
    {
        return !!$this->deprecationReason;
    }

    /**
     * @return callable|\Closure
     */
    public function getComplexityFn()
    {
        return $this->complexityFn;
    }

    /**
     * @param $childrenComplexity
     * @return mixed
     */
    public static function defaultComplexity($childrenComplexity)
    {
        return $childrenComplexity + 1;
    }
}
