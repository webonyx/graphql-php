<?php
namespace GraphQL;

use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Introspection;

class Schema
{
    /**
     * @var ObjectType
     */
    protected $_queryType;

    /**
     * @var ObjectType
     */
    protected $_mutationType;

    /**
     * @var ObjectType
     */
    protected $_subscriptionType;

    /**
     * @var Directive[]
     */
    protected $_directives;

    /**
     * @var array<string, Type>
     */
    protected $_typeMap;

    /**
     * @var array<string, ObjectType[]>
     */
    protected $_implementations;

    /**
     * @var array<string, array<string, boolean>>
     */
    protected $_possibleTypeMap;

    /**
     * Schema constructor.
     * @param array $config
     */
    public function __construct($config = null)
    {
        if (func_num_args() > 1 || $config instanceof Type) {
            trigger_error(
                'GraphQL\Schema constructor expects config object now instead of types passed as arguments. '.
                'See https://github.com/webonyx/graphql-php/issues/36',
                E_USER_DEPRECATED
            );
            list($queryType, $mutationType, $subscriptionType) = func_get_args() + [null, null, null];

            $config = [
                'query' => $queryType,
                'mutation' => $mutationType,
                'subscription' => $subscriptionType
            ];
        }

        $this->_init($config);
    }

    protected function _init(array $config)
    {
        $config += [
            'query' => null,
            'mutation' => null,
            'subscription' => null,
            'types' => [],
            'directives' => [],
            'validate' => true
        ];

        Utils::invariant(
            $config['query'] instanceof ObjectType,
            "Schema query must be Object Type but got: " . Utils::getVariableType($config['query'])
        );

        $this->_queryType = $config['query'];

        Utils::invariant(
            !$config['mutation'] || $config['mutation'] instanceof ObjectType,
            "Schema mutation must be Object Type if provided but got: " . Utils::getVariableType($config['mutation'])
        );
        $this->_mutationType = $config['mutation'];

        Utils::invariant(
            !$config['subscription'] || $config['subscription'] instanceof ObjectType,
            "Schema subscription must be Object Type if provided but got: " . Utils::getVariableType($config['subscription'])
        );
        $this->_subscriptionType = $config['subscription'];

        Utils::invariant(
            !$config['types'] || is_array($config['types']),
            "Schema types must be Array if provided but got: " . Utils::getVariableType($config['types'])
        );

        Utils::invariant(
            !$config['directives'] || (is_array($config['directives']) && Utils::every($config['directives'], function($d) {return $d instanceof Directive;})),
            "Schema directives must be Directive[] if provided but got " . Utils::getVariableType($config['directives'])
        );

        $this->_directives = array_merge($config['directives'], [
            Directive::includeDirective(),
            Directive::skipDirective()
        ]);

        // Build type map now to detect any errors within this schema.
        $initialTypes = [
            $config['query'],
            $config['mutation'],
            $config['subscription'],
            Introspection::_schema()
        ];
        if (!empty($config['types'])) {
            $initialTypes = array_merge($initialTypes, $config['types']);
        }

        foreach ($initialTypes as $type) {
            $this->_extractTypes($type);
        }
        $this->_typeMap += Type::getInternalTypes();

        // Keep track of all implementations by interface name.
        $this->_implementations = [];
        foreach ($this->_typeMap as $typeName => $type) {
            if ($type instanceof ObjectType) {
                foreach ($type->getInterfaces() as $iface) {
                    $this->_implementations[$iface->name][] = $type;
                }
            }
        }
    }

    /**
     * @return ObjectType
     */
    public function getQueryType()
    {
        return $this->_queryType;
    }

    /**
     * @return ObjectType
     */
    public function getMutationType()
    {
        return $this->_mutationType;
    }

    /**
     * @return ObjectType
     */
    public function getSubscriptionType()
    {
        return $this->_subscriptionType;
    }

    /**
     * @return array
     */
    public function getTypeMap()
    {
        return $this->_typeMap;
    }

    /**
     * @param string $name
     * @return Type
     */
    public function getType($name)
    {
        $map = $this->getTypeMap();
        return isset($map[$name]) ? $map[$name] : null;
    }

    /**
     * @param AbstractType $abstractType
     * @return ObjectType[]
     */
    public function getPossibleTypes(AbstractType $abstractType)
    {
        if ($abstractType instanceof UnionType) {
            return $abstractType->getTypes();
        }
        Utils::invariant($abstractType instanceof InterfaceType);
        return isset($this->_implementations[$abstractType->name]) ? $this->_implementations[$abstractType->name] : [];
    }

    /**
     * @param AbstractType $abstractType
     * @param ObjectType $possibleType
     * @return bool
     */
    public function isPossibleType(AbstractType $abstractType, ObjectType $possibleType)
    {
        if (null === $this->_possibleTypeMap) {
            $this->_possibleTypeMap = [];
        }

        if (!isset($this->_possibleTypeMap[$abstractType->name])) {
            $tmp = [];
            foreach ($this->getPossibleTypes($abstractType) as $type) {
                $tmp[$type->name] = true;
            }
            $this->_possibleTypeMap[$abstractType->name] = $tmp;
        }

        return !empty($this->_possibleTypeMap[$abstractType->name][$possibleType->name]);
    }

    /**
     * @return Directive[]
     */
    public function getDirectives()
    {
        return $this->_directives;
    }

    /**
     * @param $name
     * @return Directive
     */
    public function getDirective($name)
    {
        foreach ($this->getDirectives() as $directive) {
            if ($directive->name === $name) {
                return $directive;
            }
        }
        return null;
    }

    protected function _extractTypes($type)
    {
        if (!$type) {
            return $this->_typeMap;
        }

        if ($type instanceof WrappingType) {
            return $this->_extractTypes($type->getWrappedType(true));
        }

        if (!empty($this->_typeMap[$type->name])) {
            Utils::invariant(
                $this->_typeMap[$type->name] === $type,
                "Schema must contain unique named types but contains multiple types named \"$type\"."
            );
            return $this->_typeMap;
        }
        $this->_typeMap[$type->name] = $type;

        $nestedTypes = [];

        if ($type instanceof UnionType) {
            $nestedTypes = $type->getTypes();
        }
        if ($type instanceof ObjectType) {
            $nestedTypes = array_merge($nestedTypes, $type->getInterfaces());
        }
        if ($type instanceof ObjectType || $type instanceof InterfaceType || $type instanceof InputObjectType) {
            foreach ((array) $type->getFields() as $fieldName => $field) {
                if (isset($field->args)) {
                    $fieldArgTypes = array_map(function($arg) { return $arg->getType(); }, $field->args);
                    $nestedTypes = array_merge($nestedTypes, $fieldArgTypes);
                }
                $nestedTypes[] = $field->getType();
            }
        }
        foreach ($nestedTypes as $type) {
            $this->_extractTypes($type);
        }
        return $this->_typeMap;
    }
}
