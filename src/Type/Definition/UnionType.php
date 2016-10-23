<?php
namespace GraphQL\Type\Definition;

use GraphQL\Type\DefinitionContainer;
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
     * @var array<string, ObjectType>
     */
    private $possibleTypeNames;

    /**
     * @var callback
     */
    private $resolveTypeFn;

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
            'name' => Config::STRING | Config::REQUIRED,
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
        $this->resolveTypeFn = isset($config['resolveType']) ? $config['resolveType'] : null;
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

            // TODO: Return some sort of generator to avoid multiple loops
            $this->types = Utils::map($types, function($type) {
                return $type instanceof DefinitionContainer ? $type->getDefinition() : $type;
            });
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
     * @return callable|null
     */
    public function getResolveTypeFn()
    {
        return $this->resolveTypeFn;
    }
}
