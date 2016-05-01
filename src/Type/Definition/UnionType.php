<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

class UnionType extends Type implements AbstractType, OutputType, CompositeType
{
    /**
     * @var ObjectType[]
     */
    private $_types;

    /**
     * @var array<string, ObjectType>
     */
    private $_possibleTypeNames;

    /**
     * @var callback
     */
    private $_resolveTypeFn;

    /**
     * @var array
     */
    private $_config;

    public function __construct($config)
    {
        Config::validate($config, [
            'name' => Config::STRING | Config::REQUIRED,
            'types' => Config::arrayOf(Config::OBJECT_TYPE, Config::MAYBE_THUNK | Config::REQUIRED),
            'resolveType' => Config::CALLBACK, // function($value, ResolveInfo $info) => ObjectType
            'description' => Config::STRING
        ]);

        Utils::invariant(!empty($config['types']), "");

        /**
         * Optionally provide a custom type resolver function. If one is not provided,
         * the default implemenation will call `isTypeOf` on each implementing
         * Object type.
         */
        $this->name = $config['name'];
        $this->description = isset($config['description']) ? $config['description'] : null;
        $this->_types = $config['types'];
        $this->_resolveTypeFn = isset($config['resolveType']) ? $config['resolveType'] : null;
        $this->_config = $config;
    }

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
        if ($this->_types instanceof \Closure) {
            $this->_types = call_user_func($this->_types);
        }
        return $this->_types;
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

        if (null === $this->_possibleTypeNames) {
            $this->_possibleTypeNames = [];
            foreach ($this->getTypes() as $possibleType) {
                $this->_possibleTypeNames[$possibleType->name] = true;
            }
        }
        return isset($this->_possibleTypeNames[$type->name]);
    }

    /**
     * @return callable|null
     */
    public function getResolveTypeFn()
    {
        return $this->_resolveTypeFn;
    }
}
