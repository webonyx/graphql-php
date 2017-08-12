<?php
namespace GraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\TypeComparators;
use GraphQL\Utils\TypeInfo;
use GraphQL\Utils\Utils;

/**
 * Schema Definition
 *
 * A Schema is created by supplying the root types of each type of operation:
 * query, mutation (optional) and subscription (optional). A schema definition is
 * then supplied to the validator and executor.
 *
 * Example:
 *
 *     $schema = new GraphQL\Type\Schema([
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
     * @var SchemaConfig
     */
    private $config;

    /**
     * Contains actual descriptor for this schema
     *
     * @var Descriptor
     */
    private $descriptor;

    /**
     * Contains currently resolved schema types
     *
     * @var Type[]
     */
    private $resolvedTypes = [];

    /**
     * True when $resolvedTypes contain all possible schema types
     *
     * @var bool
     */
    private $fullyLoaded = false;

    /**
     * Schema constructor.
     *
     * @param array|SchemaConfig $config
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
        if (is_array($config)) {
            $config = SchemaConfig::create($config);
        }

        Utils::invariant(
            $config instanceof SchemaConfig,
            'Schema constructor expects instance of GraphQL\Type\SchemaConfig or an array with keys: %s; but got: %s',
            implode(', ', [
                'query',
                'mutation',
                'subscription',
                'types',
                'directives',
                'typeLoader',
                'descriptor'
            ]),
            Utils::getVariableType($config)
        );

        Utils::invariant(
            $config->query instanceof ObjectType,
            "Schema query must be Object Type but got: " . Utils::getVariableType($config->query)
        );

        $this->config = $config;
    }

    /**
     * Returns schema query type
     *
     * @return ObjectType
     */
    public function getQueryType()
    {
        return $this->config->query;
    }

    /**
     * Returns schema mutation type
     *
     * @return ObjectType|null
     */
    public function getMutationType()
    {
        return $this->config->mutation;
    }

    /**
     * Returns schema subscription
     *
     * @return ObjectType|null
     */
    public function getSubscriptionType()
    {
        return $this->config->subscription;
    }

    /**
     * @return SchemaConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Returns array of all types in this schema. Keys of this array represent type names, values are instances
     * of corresponding type definitions
     *
     * @return Type[]
     */
    public function getTypeMap()
    {
        if (!$this->fullyLoaded) {
            if ($this->config->descriptor && $this->config->typeLoader) {
                // Following is still faster than $this->collectAllTypes() because it won't init fields
                $typesToResolve = array_diff_key($this->config->descriptor->typeMap, $this->resolvedTypes);
                foreach ($typesToResolve as $typeName => $_) {
                    $this->resolvedTypes[$typeName] = $this->loadType($typeName);
                }
            } else {
                $this->resolvedTypes = $this->collectAllTypes();
            }
            $this->fullyLoaded = true;
        }
        return $this->resolvedTypes;
    }

    /**
     * Returns type by it's name
     *
     * @param string $name
     * @return Type
     */
    public function getType($name)
    {
        return $this->resolveType($name);
    }

    /**
     * Returns serializable schema descriptor which can be passed later
     * to Schema config to enable a set of performance optimizations
     *
     * @return Descriptor
     */
    public function describe()
    {
        if ($this->descriptor) {
            return $this->descriptor;
        }

        $this->resolvedTypes = $this->collectAllTypes();
        $this->fullyLoaded = true;

        $descriptor = new Descriptor();
        $descriptor->version = '1.0';
        $descriptor->created = time();

        foreach ($this->resolvedTypes as $type) {
            if ($type instanceof ObjectType) {
                foreach ($type->getInterfaces() as $interface) {
                    $descriptor->possibleTypeMap[$interface->name][$type->name] = 1;
                }
            } else if ($type instanceof UnionType) {
                foreach ($type->getTypes() as $innerType) {
                    $descriptor->possibleTypeMap[$type->name][$innerType->name] = 1;
                }
            }
            $descriptor->typeMap[$type->name] = 1;
        }

        return $this->descriptor = $descriptor;
    }

    private function collectAllTypes()
    {
        $initialTypes = array_merge(
            [
                $this->config->query,
                $this->config->mutation,
                $this->config->subscription,
                Introspection::_schema()
            ],
            array_values($this->resolvedTypes)
        );

        $typeMap = [];
        foreach ($initialTypes as $type) {
            $typeMap = TypeInfo::extractTypes($type, $typeMap);
        }

        $types = $this->config->types;
        if (is_callable($types)) {
            $types = $types();

            Utils::invariant(
                is_array($types) || $types instanceof \Traversable,
                'Schema types callable must return array or instance of Traversable but got: %s',
                Utils::getVariableType($types)
            );
        }

        if (!empty($types)) {
            foreach ($types as $type) {
                Utils::invariant(
                    $type instanceof Type,
                    'Each entry of schema types must be instance of GraphQL\Type\Definition\Type but got: %s',
                    Utils::getVariableType($types)
                );
                $typeMap = TypeInfo::extractTypes($type, $typeMap);
            }
        }

        return $typeMap + Type::getInternalTypes();
    }

    /**
     * Returns all possible concrete types for given abstract type
     * (implementations for interfaces and members of union type for unions)
     *
     * @param AbstractType $abstractType
     * @return ObjectType[]
     */
    public function getPossibleTypes(AbstractType $abstractType)
    {
        if ($abstractType instanceof UnionType) {
            return $abstractType->getTypes();
        }

        /** @var InterfaceType $abstractType */
        $descriptor = $this->config->descriptor ?: $this->describe();

        $result = [];
        if (isset($descriptor->possibleTypeMap[$abstractType->name])) {
            foreach ($descriptor->possibleTypeMap[$abstractType->name] as $typeName => $_) {
                $result[] = $this->resolveType($typeName);
            }
        }
        return $result;
    }

    /**
     * Accepts name of type or type instance and returns type instance. If type with given name is not loaded yet -
     * will load it first.
     *
     * @param $typeOrName
     * @return Type
     */
    public function resolveType($typeOrName)
    {
        if ($typeOrName instanceof Type) {
            if ($typeOrName->name && !isset($this->resolvedTypes[$typeOrName->name])) {
                $this->resolvedTypes[$typeOrName->name] = $typeOrName;
            }
            return $typeOrName;
        }
        if (!isset($this->resolvedTypes[$typeOrName])) {
            $this->resolvedTypes[$typeOrName] = $this->loadType($typeOrName);
        }
        return $this->resolvedTypes[$typeOrName];
    }

    private function loadType($typeName)
    {
        $typeLoader = $this->config->typeLoader;

        if (!$typeLoader) {
            return $this->defaultTypeLoader($typeName);
        }

        $type = $typeLoader($typeName);
        // TODO: validate returned value
        return $type;
    }

    /**
     * Returns true if object type is concrete type of given abstract type
     * (implementation for interfaces and members of union type for unions)
     *
     * @param AbstractType $abstractType
     * @param ObjectType $possibleType
     * @return bool
     */
    public function isPossibleType(AbstractType $abstractType, ObjectType $possibleType)
    {
        if ($this->config->descriptor) {
            return !empty($this->config->descriptor->possibleTypeMap[$abstractType->name][$possibleType->name]);
        }

        if ($abstractType instanceof InterfaceType) {
            return $possibleType->implementsInterface($abstractType);
        }

        /** @var UnionType $abstractType */
        return $abstractType->isPossibleType($possibleType);
    }

    /**
     * Returns a list of directives supported by this schema
     *
     * @return Directive[]
     */
    public function getDirectives()
    {
        return $this->config->directives ?: GraphQL::getInternalDirectives();
    }

    /**
     * Returns instance of directive by name
     *
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
     * @param $typeName
     * @return Type
     */
    private function defaultTypeLoader($typeName)
    {
        // Default type loader simply fallbacks to collecting all types
        if (!$this->fullyLoaded) {
            $this->resolvedTypes = $this->collectAllTypes();
            $this->fullyLoaded = true;
        }
        if (!isset($this->resolvedTypes[$typeName])) {
            return null;
        }
        return $this->resolvedTypes[$typeName];
    }


    /**
     * @throws InvariantViolation
     */
    public function assertValid()
    {
        foreach ($this->config->getDirectives() as $index => $directive) {
            Utils::invariant(
                $directive instanceof Directive,
                "Each entry of \"directives\" option of Schema config must be an instance of %s but entry at position %d is %s.",
                Directive::class,
                $index,
                Utils::printSafe($directive)
            );
        }

        foreach ($this->getTypeMap() as $name => $type) {
            $type->assertValid();

            if ($type instanceof AbstractType) {
                $possibleTypes = $this->getPossibleTypes($type);

                Utils::invariant(
                    !empty($possibleTypes),
                    "Could not find possible implementing types for {$type->name} " .
                    'in schema. Check that schema.types is defined and is an array of ' .
                    'all possible types in the schema.'
                );

            } else if ($type instanceof ObjectType) {
                foreach ($type->getInterfaces() as $iface) {
                    $this->assertImplementsIntarface($type, $iface);
                }
            }
        }
    }

    private function assertImplementsIntarface(ObjectType $object, InterfaceType $iface)
    {
        $objectFieldMap = $object->getFields();
        $ifaceFieldMap  = $iface->getFields();

        // Assert each interface field is implemented.
        foreach ($ifaceFieldMap as $fieldName => $ifaceField) {

            // Assert interface field exists on object.
            Utils::invariant(
                isset($objectFieldMap[$fieldName]),
                "{$iface->name} expects field \"{$fieldName}\" but {$object->name} does not provide it"
            );

            $objectField = $objectFieldMap[$fieldName];

            // Assert interface field type is satisfied by object field type, by being
            // a valid subtype. (covariant)
            Utils::invariant(
                TypeComparators::isTypeSubTypeOf($this, $objectField->getType(), $ifaceField->getType()),
                "{$iface->name}.{$fieldName} expects type \"{$ifaceField->getType()}\" " .
                "but " .
                "{$object->name}.${fieldName} provides type \"{$objectField->getType()}\""
            );

            // Assert each interface field arg is implemented.
            foreach ($ifaceField->args as $ifaceArg) {
                $argName = $ifaceArg->name;

                /** @var FieldArgument $objectArg */
                $objectArg = Utils::find($objectField->args, function(FieldArgument $arg) use ($argName) {
                    return $arg->name === $argName;
                });

                // Assert interface field arg exists on object field.
                Utils::invariant(
                    $objectArg,
                    "{$iface->name}.{$fieldName} expects argument \"{$argName}\" but ".
                    "{$object->name}.{$fieldName} does not provide it."
                );

                // Assert interface field arg type matches object field arg type.
                // (invariant)
                Utils::invariant(
                    TypeComparators::isEqualType($ifaceArg->getType(), $objectArg->getType()),
                    "{$iface->name}.{$fieldName}({$argName}:) expects type " .
                    "\"{$ifaceArg->getType()->name}\" but " .
                    "{$object->name}.{$fieldName}({$argName}:) provides type " .
                    "\"{$objectArg->getType()->name}\"."
                );

                // Assert additional arguments must not be required.
                foreach ($objectField->args as $objectArg) {
                    $argName = $objectArg->name;
                    $ifaceArg = Utils::find($ifaceField->args, function(FieldArgument $arg) use ($argName) {
                        return $arg->name === $argName;
                    });
                    if (!$ifaceArg) {
                        Utils::invariant(
                            !($objectArg->getType() instanceof NonNull),
                            "{$object->name}.{$fieldName}({$argName}:) is of required type " .
                            "\"{$objectArg->getType()}\" but is not also provided by the " .
                            "interface {$iface->name}.{$fieldName}."
                        );
                    }
                }
            }
        }
    }
}
