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

/**
 * Schema Definition
 *
 * A Schema is created by supplying the root types of each type of operation:
 * query, mutation (optional) and subscription (optional). A schema definition is
 * then supplied to the validator and executor.
 *
 * Example:
 *
 *     $schema = new GraphQL\Schema([
 *       'query' => $MyAppQueryRootType,
 *       'mutation' => $MyAppMutationRootType,
 *     ]);
 *
 * Note: If an array of `directives` are provided to GraphQL\Schema, that will be
 * the exact list of directives represented and allowed. If `directives` is not
 * provided then a default set of the specified directives (e.g. @include and
 * @skip) will be used. If you wish to provide *additional* directives to these
 * specified directives, you must explicitly declare them. Example:
 *
 *     $mySchema = new GraphQL\Schema([
 *       ...
 *       'directives' => array_merge(GraphQL::getInternalDirectives(), [ $myCustomDirective ]),
 *     ])
 *
 * @package GraphQL
 */
class Schema
{
    /**
     * @var ObjectType
     */
    private $queryType;

    /**
     * @var ObjectType
     */
    private $mutationType;

    /**
     * @var ObjectType
     */
    private $subscriptionType;

    /**
     * @var Directive[]
     */
    private $directives;

    /**
     * @var Type[]
     */
    private $typeMap;

    /**
     * @var array<string, ObjectType[]>
     */
    private $implementations;

    /**
     * @var array<string, array<string, boolean>>
     */
    private $possibleTypeMap;

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

        $this->init($config);
    }

    /**
     * @param array $config
     */
    private function init(array $config)
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

        $this->queryType = $config['query'];

        Utils::invariant(
            !$config['mutation'] || $config['mutation'] instanceof ObjectType,
            "Schema mutation must be Object Type if provided but got: " . Utils::getVariableType($config['mutation'])
        );
        $this->mutationType = $config['mutation'];

        Utils::invariant(
            !$config['subscription'] || $config['subscription'] instanceof ObjectType,
            "Schema subscription must be Object Type if provided but got: " . Utils::getVariableType($config['subscription'])
        );
        $this->subscriptionType = $config['subscription'];

        Utils::invariant(
            !$config['types'] || is_array($config['types']),
            "Schema types must be Array if provided but got: " . Utils::getVariableType($config['types'])
        );

        Utils::invariant(
            !$config['directives'] || (is_array($config['directives']) && Utils::every($config['directives'], function($d) {return $d instanceof Directive;})),
            "Schema directives must be Directive[] if provided but got " . Utils::getVariableType($config['directives'])
        );

        $this->directives = $config['directives'] ?: GraphQL::getInternalDirectives();

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
            $this->extractTypes($type);
        }
        $this->typeMap += Type::getInternalTypes();

        // Keep track of all implementations by interface name.
        $this->implementations = [];
        foreach ($this->typeMap as $typeName => $type) {
            if ($type instanceof ObjectType) {
                foreach ($type->getInterfaces() as $iface) {
                    $this->implementations[$iface->name][] = $type;
                }
            }
        }
    }

    /**
     * @return ObjectType
     */
    public function getQueryType()
    {
        return $this->queryType;
    }

    /**
     * @return ObjectType
     */
    public function getMutationType()
    {
        return $this->mutationType;
    }

    /**
     * @return ObjectType
     */
    public function getSubscriptionType()
    {
        return $this->subscriptionType;
    }

    /**
     * @return array
     */
    public function getTypeMap()
    {
        return $this->typeMap;
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
        return isset($this->implementations[$abstractType->name]) ? $this->implementations[$abstractType->name] : [];
    }

    /**
     * @param AbstractType $abstractType
     * @param ObjectType $possibleType
     * @return bool
     */
    public function isPossibleType(AbstractType $abstractType, ObjectType $possibleType)
    {
        if (null === $this->possibleTypeMap) {
            $this->possibleTypeMap = [];
        }

        if (!isset($this->possibleTypeMap[$abstractType->name])) {
            $tmp = [];
            foreach ($this->getPossibleTypes($abstractType) as $type) {
                $tmp[$type->name] = true;
            }

            Utils::invariant(
                !empty($tmp),
                'Could not find possible implementing types for $%s ' .
                'in schema. Check that schema.types is defined and is an array of ' .
                'all possible types in the schema.',
                $abstractType->name
            );

            $this->possibleTypeMap[$abstractType->name] = $tmp;
        }

        return !empty($this->possibleTypeMap[$abstractType->name][$possibleType->name]);
    }

    /**
     * @return Directive[]
     */
    public function getDirectives()
    {
        return $this->directives;
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

    /**
     * @param $type
     * @return array
     */
    private function extractTypes($type)
    {
        if (!$type) {
            return $this->typeMap;
        }

        if ($type instanceof WrappingType) {
            return $this->extractTypes($type->getWrappedType(true));
        }

        if (!empty($this->typeMap[$type->name])) {
            Utils::invariant(
                $this->typeMap[$type->name] === $type,
                "Schema must contain unique named types but contains multiple types named \"$type\"."
            );
            return $this->typeMap;
        }
        $this->typeMap[$type->name] = $type;

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
            $this->extractTypes($type);
        }
        return $this->typeMap;
    }
}
