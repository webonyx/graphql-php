<?php
namespace GraphQL;

use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\EagerResolution;
use GraphQL\Type\Introspection;
use GraphQL\Type\Resolution;

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
     * @var array
     */
    private $config;

    /**
     * @var array<string, array<string, boolean>>
     */
    private $possibleTypeMap = [];

    /**
     * @var Resolution
     */
    private $typeResolutionStrategy;

    /**
     * Required for `getTypeMap()` and `getDescriptor()` methods
     *
     * @var EagerResolution
     */
    private $eagerTypeResolutionStrategy;

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

        $config += [
            'query' => null,
            'mutation' => null,
            'subscription' => null,
            'types' => [],
            'directives' => null,
            'typeResolution' => null
        ];

        $this->init($config);
    }

    /**
     * @param array $config
     */
    private function init(array $config)
    {
        Utils::invariant(
            $config['query'] instanceof ObjectType,
            "Schema query must be Object Type but got: " . Utils::getVariableType($config['query'])
        );

        Utils::invariant(
            !$config['mutation'] || $config['mutation'] instanceof ObjectType,
            "Schema mutation must be Object Type if provided but got: " . Utils::getVariableType($config['mutation'])
        );

        Utils::invariant(
            !$config['subscription'] || $config['subscription'] instanceof ObjectType,
            "Schema subscription must be Object Type if provided but got: " . Utils::getVariableType($config['subscription'])
        );

        Utils::invariant(
            !$config['types'] || is_array($config['types']),
            "Schema types must be Array if provided but got: " . Utils::getVariableType($config['types'])
        );

        Utils::invariant(
            !$config['directives'] || (is_array($config['directives']) && Utils::every($config['directives'], function($d) {return $d instanceof Directive;})),
            "Schema directives must be Directive[] if provided but got " . Utils::getVariableType($config['directives'])
        );

        Utils::invariant(
            !$config['typeResolution'] || $config['typeResolution'] instanceof Resolution,
            "Type resolution strategy is expected to be instance of GraphQL\\Type\\Resolution, but got " .
            Utils::getVariableType($config['typeResolution'])
        );

        $this->config = $config;
        $this->typeResolutionStrategy = $config['typeResolution'] ?: $this->getEagerTypeResolutionStrategy();
    }

    /**
     * @return ObjectType
     */
    public function getQueryType()
    {
        return $this->config['query'];
    }

    /**
     * @return ObjectType
     */
    public function getMutationType()
    {
        return $this->config['mutation'];
    }

    /**
     * @return ObjectType
     */
    public function getSubscriptionType()
    {
        return $this->config['subscription'];
    }

    /**
     * Returns full map of types in this schema.
     * Note: internally it will eager-load all types using GraphQL\Type\EagerResolution strategy
     *
     * @return Type[]
     */
    public function getTypeMap()
    {
        return $this->getEagerTypeResolutionStrategy()->getTypeMap();
    }

    /**
     * @param string $name
     * @return Type
     */
    public function getType($name)
    {
        return $this->typeResolutionStrategy->resolveType($name);
    }

    /**
     * Returns serializable schema representation suitable for GraphQL\Type\LazyResolution
     *
     * @return array
     */
    public function getDescriptor()
    {
        return $this->getEagerTypeResolutionStrategy()->getDescriptor();
    }

    /**
     * @param AbstractType $abstractType
     * @return ObjectType[]
     */
    public function getPossibleTypes(AbstractType $abstractType)
    {
        return $this->typeResolutionStrategy->resolvePossibleTypes($abstractType);
    }

    /**
     * @param AbstractType $abstractType
     * @param ObjectType $possibleType
     * @return bool
     */
    public function isPossibleType(AbstractType $abstractType, ObjectType $possibleType)
    {
        if (!isset($this->possibleTypeMap[$abstractType->name])) {
            $tmp = [];
            foreach ($this->getPossibleTypes($abstractType) as $type) {
                $tmp[$type->name] = 1;
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
        return isset($this->config['directives']) ? $this->config['directives'] : GraphQL::getInternalDirectives();
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

    private function getEagerTypeResolutionStrategy()
    {
        if (!$this->eagerTypeResolutionStrategy) {
            if ($this->typeResolutionStrategy instanceof EagerResolution) {
                $this->eagerTypeResolutionStrategy = $this->typeResolutionStrategy;
            } else {
                // Build type map now to detect any errors within this schema.
                $initialTypes = [
                    $this->config['query'],
                    $this->config['mutation'],
                    $this->config['subscription'],
                    Introspection::_schema()
                ];
                if (!empty($this->config['types'])) {
                    $initialTypes = array_merge($initialTypes, $this->config['types']);
                }
                $this->eagerTypeResolutionStrategy = new EagerResolution($initialTypes);
            }
        }
        return $this->eagerTypeResolutionStrategy;
    }
}
