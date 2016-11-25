<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

/**
 * Class UnionType
 * @package GraphQL\Type\Definition
 */
class UnionType extends Type implements AbstractType, OutputType, CompositeType
{
    /**
     * @var ObjectType[]
     */
    private $types;

    /**
     * @var ObjectType[]
     */
    private $possibleTypeNames;

    /**
     * @var array
     */
    public $config;

    /**
     * UnionType constructor.
     * @param $config
     */
    public function __construct($config)
    {
        if (!isset($config['name'])) {
            $config['name'] = $this->tryInferName();
        }

        Config::validate($config, [
            'name' => Config::NAME | Config::REQUIRED,
            'types' => Config::arrayOf(Config::OBJECT_TYPE, Config::MAYBE_THUNK | Config::REQUIRED),
            'resolveType' => Config::CALLBACK, // function($value, ResolveInfo $info) => ObjectType
            'description' => Config::STRING
        ]);

        /**
         * Optionally provide a custom type resolver function. If one is not provided,
         * the default implemenation will call `isTypeOf` on each implementing
         * Object type.
         */
        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->config = $config;
    }

    /**
     * @return ObjectType[]
     */
    public function getPossibleTypes()
    {
        trigger_error(__METHOD__ . ' is deprecated in favor of ' . __CLASS__ . '::getTypes()', E_USER_DEPRECATED);
        return $this->getTypes();
    }

    /**
     * @return ObjectType[]
     */
    public function getTypes()
    {
        if (null === $this->types) {
            if ($this->config['types'] instanceof \Closure) {
                $types = call_user_func($this->config['types']);
            } else {
                $types = $this->config['types'];
            }

            Utils::invariant(
                is_array($types),
                'Option "types" of union "%s" is expected to return array of types (or closure returning array of types)',
                $this->name
            );

            $this->types = [];
            foreach ($types as $type) {
                $this->types[] = Type::resolve($type);
            }
        }
        return $this->types;
    }

    /**
     * @param Type $type
     * @return mixed
     */
    public function isPossibleType(Type $type)
    {
        if (!$type instanceof ObjectType) {
            return false;
        }

        if (null === $this->possibleTypeNames) {
            $this->possibleTypeNames = [];
            foreach ($this->getTypes() as $possibleType) {
                $this->possibleTypeNames[$possibleType->name] = true;
            }
        }
        return isset($this->possibleTypeNames[$type->name]);
    }

    /**
     * Resolves concrete ObjectType for given object value
     *
     * @param $objectValue
     * @param $context
     * @param ResolveInfo $info
     * @return callable|null
     */
    public function resolveType($objectValue, $context, ResolveInfo $info)
    {
        if (isset($this->config['resolveType'])) {
            $fn = $this->config['resolveType'];
            return $fn($objectValue, $context, $info);
        }
        return null;
    }
}
