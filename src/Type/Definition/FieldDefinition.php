<?php
namespace GraphQL\Type\Definition;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Utils\Utils;

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
     * @var FieldDefinitionNode|null
     */
    public $astNode;

    /**
     * Original field definition config
     *
     * @var array
     */
    public $config;

    /**
     * @var OutputType
     */
    private $type;

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

    public static function defineFieldMap(Type $type, $fields)
    {
        if (is_callable($fields)) {
            $fields = $fields();
        }
        if (!is_array($fields)) {
            throw new InvariantViolation(
                "{$type->name} fields must be an array or a callable which returns such an array."
            );
        }
        $map = [];
        foreach ($fields as $name => $field) {
            if (is_array($field)) {
                if (!isset($field['name']) && is_string($name)) {
                    $field['name'] = $name;
                }
                if (isset($field['args']) && !is_array($field['args'])) {
                    throw new InvariantViolation(
                        "{$type->name}.{$name} args must be an array."
                    );
                }
                $fieldDef = self::create($field);
            } else if ($field instanceof FieldDefinition) {
                $fieldDef = $field;
            } else {
                if (is_string($name) && $field) {
                    $fieldDef = self::create(['name' => $name, 'type' => $field]);
                } else {
                    throw new InvariantViolation(
                        "{$type->name}.$name field config must be an array, but got: " . Utils::printSafe($field)
                    );
                }
            }
            $map[$fieldDef->name] = $fieldDef;
        }
        return $map;
    }

    /**
     * @param array $fields
     * @param string $parentTypeName
     * @deprecated use defineFieldMap instead
     * @return array
     */
    public static function createMap(array $fields, $parentTypeName = null)
    {
        trigger_error(
            __METHOD__ . ' is deprecated, use ' . __CLASS__ . '::defineFieldMap() instead',
            E_USER_DEPRECATED
        );

        $map = [];
        foreach ($fields as $name => $field) {
            if (is_array($field)) {
                if (!isset($field['name']) && is_string($name)) {
                    $field['name'] = $name;
                }
                $fieldDef = self::create($field);
            } else if ($field instanceof FieldDefinition) {
                $fieldDef = $field;
            } else {
                if (is_string($name)) {
                    $fieldDef = self::create(['name' => $name, 'type' => $field]);
                } else {
                    throw new InvariantViolation(
                        "Unexpected field definition for type $parentTypeName at field $name: " . Utils::printSafe($field)
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

	    // with lazy loading we might need to take resolveFn and args from the type
	    $this->resolveFn = isset($config['resolve']) ? $config['resolve'] : $this->getPropertyFromType('resolve');

	    $args = isset($config['args']) ? $config['args'] : $this->getPropertyFromType('args');
	    $this->args = is_array($args) ? FieldArgument::createMap($args) : [];

        $this->mapFn = isset($config['map']) ? $config['map'] : null;

        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->deprecationReason = isset($config['deprecationReason']) ? $config['deprecationReason'] : null;
        $this->astNode = isset($config['astNode']) ? $config['astNode'] : null;

        $this->config = $config;

        $this->complexityFn = isset($config['complexity']) ? $config['complexity'] : static::DEFAULT_COMPLEXITY_FN;
    }

	/**
	 * return the value of field definition property from the field type.
	 * This is useful with lazy loading, when we don't define the field completely,
	 * but denote only the type of field
	 *
	 * @param $propertyName
	 * @return null|mixed
	 */
	private function getPropertyFromType($propertyName) {
		if ($this->type instanceof Type) {
			return isset($this->type->config[$propertyName]) ? $this->type->config[$propertyName] : null;
		}

		return null;
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
        return $this->type;
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
     * @param Type $parentType
     * @throws InvariantViolation
     */
    public function assertValid(Type $parentType)
    {
        try {
            Utils::assertValidName($this->name);
        } catch (InvariantViolation $e) {
            throw new InvariantViolation("{$parentType->name}.{$this->name}: {$e->getMessage()}");
        }
        Utils::invariant(
            !isset($this->config['isDeprecated']),
            "{$parentType->name}.{$this->name} should provide \"deprecationReason\" instead of \"isDeprecated\"."
        );

        $type = $this->type;
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType(true);
        }
        Utils::invariant(
            $type instanceof OutputType,
            "{$parentType->name}.{$this->name} field type must be Output Type but got: " . Utils::printSafe($this->type)
        );
        Utils::invariant(
            $this->resolveFn === null || is_callable($this->resolveFn),
            "{$parentType->name}.{$this->name} field resolver must be a function if provided, but got: %s",
            Utils::printSafe($this->resolveFn)
        );
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
