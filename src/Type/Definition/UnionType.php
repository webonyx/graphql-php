<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

class UnionType extends Type implements AbstractType, OutputType, CompositeType
{
    /**
     * @var Array<GraphQLObjectType>
     */
    private $_types;

    /**
     * @var array<string, ObjectType>
     */
    private $_possibleTypeNames;

    /**
     * @var callback
     */
    private $_resolveType;

    /**
     * @var array
     */
    private $_config;

    public function __construct($config)
    {
        Config::validate($config, [
            'name' => Config::STRING | Config::REQUIRED,
            'types' => Config::arrayOf(Config::OBJECT_TYPE, Config::REQUIRED),
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
        $this->_resolveType = isset($config['resolveType']) ? $config['resolveType'] : null;
        $this->_config = $config;
    }

    /**
     * @return array<ObjectType>
     */
    public function getPossibleTypes()
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
            foreach ($this->getPossibleTypes() as $possibleType) {
                $this->_possibleTypeNames[$possibleType->name] = true;
            }
        }
        return isset($this->_possibleTypeNames[$type->name]);
    }

    /**
     * @param ObjectType $value
     * @param ResolveInfo $info
     *
     * @return Type
     * @throws \Exception
     */
    public function getObjectType($value, ResolveInfo $info)
    {
        $resolver = $this->_resolveType;
        return $resolver ? call_user_func($resolver, $value, $info) : Type::getTypeOf($value, $info, $this);
    }
}
