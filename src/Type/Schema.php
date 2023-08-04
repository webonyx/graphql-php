<?php declare(strict_types=1);

namespace GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaExtensionNode;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ImplementingType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\InterfaceImplementations;
use GraphQL\Utils\TypeInfo;
use GraphQL\Utils\Utils;

/**
 * Schema Definition (see [schema definition docs](schema-definition.md)).
 *
 * A Schema is created by supplying the root types of each type of operation:
 * query, mutation (optional) and subscription (optional). A schema definition is
 * then supplied to the validator and executor. Usage Example:
 *
 *     $schema = new GraphQL\Type\Schema([
 *       'query' => $MyAppQueryRootType,
 *       'mutation' => $MyAppMutationRootType,
 *     ]);
 *
 * Or using Schema Config instance:
 *
 *     $config = GraphQL\Type\SchemaConfig::create()
 *         ->setQuery($MyAppQueryRootType)
 *         ->setMutation($MyAppMutationRootType);
 *
 *     $schema = new GraphQL\Type\Schema($config);
 *
 * @phpstan-import-type SchemaConfigOptions from SchemaConfig
 * @phpstan-import-type OperationType from OperationDefinitionNode
 *
 * @see \GraphQL\Tests\Type\SchemaTest
 */
class Schema
{
    private SchemaConfig $config;

    /**
     * Contains currently resolved schema types.
     *
     * @var array<string, Type&NamedType>
     */
    private array $resolvedTypes = [];

    /**
     * Lazily initialised.
     *
     * @var array<string, InterfaceImplementations>
     */
    private array $implementationsMap;

    /** True when $resolvedTypes contains all possible schema types. */
    private bool $fullyLoaded = false;

    /** @var array<int, Error> */
    private array $validationErrors;

    public ?SchemaDefinitionNode $astNode;

    /** @var array<int, SchemaExtensionNode> */
    public array $extensionASTNodes = [];

    /**
     * @param SchemaConfig|array<string, mixed> $config
     *
     * @phpstan-param SchemaConfig|SchemaConfigOptions $config
     *
     * @api
     */
    public function __construct($config)
    {
        if (\is_array($config)) {
            $config = SchemaConfig::create($config);
        }

        // If this schema was built from a source known to be valid, then it may be
        // marked with assumeValid to avoid an additional type system validation.
        if ($config->getAssumeValid()) {
            $this->validationErrors = [];
        }

        $this->astNode = $config->astNode;
        $this->extensionASTNodes = $config->extensionASTNodes;

        $this->config = $config;
    }

    /**
     * Returns all types in this schema.
     *
     * This operation requires a full schema scan. Do not use in production environment.
     *
     * @throws InvariantViolation
     *
     * @return array<string, Type&NamedType> Keys represent type names, values are instances of corresponding type definitions
     *
     * @api
     */
    public function getTypeMap(): array
    {
        if (! $this->fullyLoaded) {
            $types = $this->config->types;
            if (\is_callable($types)) {
                $types = $types();
            }

            // Reset order of user provided types, since calls to getType() may have loaded them
            $this->resolvedTypes = [];

            foreach ($types as $typeOrLazyType) {
                /** @var Type|callable(): Type $typeOrLazyType */
                $type = self::resolveType($typeOrLazyType);
                assert($type instanceof NamedType);

                /** @var string $typeName Necessary assertion for PHPStan + PHP 8.2 */
                $typeName = $type->name;
                assert(
                    ! isset($this->resolvedTypes[$typeName]) || $type === $this->resolvedTypes[$typeName],
                    "Schema must contain unique named types but contains multiple types named \"{$type}\" (see https://webonyx.github.io/graphql-php/type-definitions/#type-registry).",
                );

                $this->resolvedTypes[$typeName] = $type;
            }

            // To preserve order of user-provided types, we add first to add them to
            // the set of "collected" types, so `collectReferencedTypes` ignore them.
            /** @var array<string, Type&NamedType> $allReferencedTypes */
            $allReferencedTypes = [];
            foreach ($this->resolvedTypes as $type) {
                // When we ready to process this type, we remove it from "collected" types
                // and then add it together with all dependent types in the correct position.
                unset($allReferencedTypes[$type->name]);
                TypeInfo::extractTypes($type, $allReferencedTypes);
            }

            foreach ([$this->config->query, $this->config->mutation, $this->config->subscription] as $rootType) {
                if ($rootType instanceof ObjectType) {
                    TypeInfo::extractTypes($rootType, $allReferencedTypes);
                }
            }

            foreach ($this->getDirectives() as $directive) {
                // @phpstan-ignore-next-line generics are not strictly enforceable, error will be caught during schema validation
                if ($directive instanceof Directive) {
                    TypeInfo::extractTypesFromDirectives($directive, $allReferencedTypes);
                }
            }
            TypeInfo::extractTypes(Introspection::_schema(), $allReferencedTypes);

            $this->resolvedTypes = $allReferencedTypes;
            $this->fullyLoaded = true;
        }

        return $this->resolvedTypes;
    }

    /**
     * Returns a list of directives supported by this schema.
     *
     * @throws InvariantViolation
     *
     * @return array<Directive>
     *
     * @api
     */
    public function getDirectives(): array
    {
        return $this->config->directives ?? GraphQL::getStandardDirectives();
    }

    /** @param mixed $typeLoaderReturn could be anything */
    public static function typeLoaderNotType($typeLoaderReturn): string
    {
        $typeClass = Type::class;
        $notType = Utils::printSafe($typeLoaderReturn);

        return "Type loader is expected to return an instanceof {$typeClass}, but it returned {$notType}";
    }

    public static function typeLoaderWrongTypeName(string $expectedTypeName, string $actualTypeName): string
    {
        return "Type loader is expected to return type {$expectedTypeName}, but it returned type {$actualTypeName}.";
    }

    /** Returns root type by operation name. */
    public function getOperationType(string $operation): ?ObjectType
    {
        switch ($operation) {
            case 'query': return $this->getQueryType();
            case 'mutation': return $this->getMutationType();
            case 'subscription': return $this->getSubscriptionType();
            default: return null;
        }
    }

    /**
     * Returns root query type.
     *
     * @api
     */
    public function getQueryType(): ?ObjectType
    {
        return $this->config->query;
    }

    /**
     * Returns root mutation type.
     *
     * @api
     */
    public function getMutationType(): ?ObjectType
    {
        return $this->config->mutation;
    }

    /**
     * Returns schema subscription.
     *
     * @api
     */
    public function getSubscriptionType(): ?ObjectType
    {
        return $this->config->subscription;
    }

    /** @api */
    public function getConfig(): SchemaConfig
    {
        return $this->config;
    }

    /**
     * Returns a type by name.
     *
     * @throws InvariantViolation
     *
     * @return (Type&NamedType)|null
     *
     * @api
     */
    public function getType(string $name): ?Type
    {
        if (isset($this->resolvedTypes[$name])) {
            return $this->resolvedTypes[$name];
        }

        $introspectionTypes = Introspection::getTypes();
        if (isset($introspectionTypes[$name])) {
            return $introspectionTypes[$name];
        }

        $standardTypes = Type::getStandardTypes();
        if (isset($standardTypes[$name])) {
            return $standardTypes[$name];
        }

        $type = $this->loadType($name);
        if ($type === null) {
            return null;
        }

        return $this->resolvedTypes[$name] = self::resolveType($type);
    }

    /** @throws InvariantViolation */
    public function hasType(string $name): bool
    {
        return $this->getType($name) !== null;
    }

    /**
     * @throws InvariantViolation
     *
     * @return (Type&NamedType)|null
     */
    private function loadType(string $typeName): ?Type
    {
        if (! isset($this->config->typeLoader)) {
            return $this->getTypeMap()[$typeName] ?? null;
        }

        $type = ($this->config->typeLoader)($typeName);
        if ($type === null) {
            return null;
        }

        // @phpstan-ignore-next-line not strictly enforceable unless PHP gets function types
        if (! $type instanceof Type) {
            throw new InvariantViolation(self::typeLoaderNotType($type));
        }

        if ($typeName !== $type->name) {
            throw new InvariantViolation(self::typeLoaderWrongTypeName($typeName, $type->name));
        }

        return $type;
    }

    /**
     * @template T of Type
     *
     * @param Type|callable $type
     *
     * @phpstan-param T|callable():T $type
     *
     * @phpstan-return T
     */
    public static function resolveType($type): Type
    {
        if ($type instanceof Type) {
            return $type;
        }

        return $type();
    }

    /**
     * Returns all possible concrete types for given abstract type
     * (implementations for interfaces and members of union type for unions).
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @param AbstractType&Type $abstractType
     *
     * @throws InvariantViolation
     *
     * @return array<ObjectType>
     *
     * @api
     */
    public function getPossibleTypes(AbstractType $abstractType): array
    {
        if ($abstractType instanceof UnionType) {
            return $abstractType->getTypes();
        }

        assert($abstractType instanceof InterfaceType, 'only other option');

        return $this->getImplementations($abstractType)->objects();
    }

    /**
     * Returns all types that implement a given interface type.
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @api
     *
     * @throws InvariantViolation
     */
    public function getImplementations(InterfaceType $abstractType): InterfaceImplementations
    {
        return $this->collectImplementations()[$abstractType->name];
    }

    /**
     * @throws InvariantViolation
     *
     * @return array<string, InterfaceImplementations>
     */
    private function collectImplementations(): array
    {
        if (! isset($this->implementationsMap)) {
            $this->implementationsMap = [];

            /**
             * @var array<
             *     string,
             *     array{
             *         objects: array<int, ObjectType>,
             *         interfaces: array<int, InterfaceType>,
             *     }
             * > $foundImplementations
             */
            $foundImplementations = [];
            foreach ($this->getTypeMap() as $type) {
                if ($type instanceof InterfaceType) {
                    if (! isset($foundImplementations[$type->name])) {
                        $foundImplementations[$type->name] = ['objects' => [], 'interfaces' => []];
                    }

                    foreach ($type->getInterfaces() as $iface) {
                        if (! isset($foundImplementations[$iface->name])) {
                            $foundImplementations[$iface->name] = ['objects' => [], 'interfaces' => []];
                        }

                        $foundImplementations[$iface->name]['interfaces'][] = $type;
                    }
                } elseif ($type instanceof ObjectType) {
                    foreach ($type->getInterfaces() as $iface) {
                        if (! isset($foundImplementations[$iface->name])) {
                            $foundImplementations[$iface->name] = ['objects' => [], 'interfaces' => []];
                        }

                        $foundImplementations[$iface->name]['objects'][] = $type;
                    }
                }
            }

            foreach ($foundImplementations as $name => $implementations) {
                $this->implementationsMap[$name] = new InterfaceImplementations($implementations['objects'], $implementations['interfaces']);
            }
        }

        return $this->implementationsMap;
    }

    /**
     * Returns true if the given type is a sub type of the given abstract type.
     *
     * @param AbstractType&Type $abstractType
     * @param ImplementingType&Type $maybeSubType
     *
     * @api
     *
     * @throws InvariantViolation
     */
    public function isSubType(AbstractType $abstractType, ImplementingType $maybeSubType): bool
    {
        if ($abstractType instanceof InterfaceType) {
            return $maybeSubType->implementsInterface($abstractType);
        }

        assert($abstractType instanceof UnionType, 'only other option');

        return $abstractType->isPossibleType($maybeSubType);
    }

    /**
     * Returns instance of directive by name.
     *
     * @api
     *
     * @throws InvariantViolation
     */
    public function getDirective(string $name): ?Directive
    {
        foreach ($this->getDirectives() as $directive) {
            if ($directive->name === $name) {
                return $directive;
            }
        }

        return null;
    }

    /**
     * Throws if the schema is not valid.
     *
     * This operation requires a full schema scan. Do not use in production environment.
     *
     * @throws Error
     * @throws InvariantViolation
     *
     * @api
     */
    public function assertValid(): void
    {
        $errors = $this->validate();

        if ($errors !== []) {
            throw new InvariantViolation(\implode("\n\n", $this->validationErrors));
        }

        $internalTypes = Type::getStandardTypes() + Introspection::getTypes();
        foreach ($this->getTypeMap() as $name => $type) {
            if (isset($internalTypes[$name])) {
                continue;
            }

            $type->assertValid();

            // Make sure type loader returns the same instance as registered in other places of schema
            if (isset($this->config->typeLoader) && $this->loadType($name) !== $type) {
                throw new InvariantViolation("Type loader returns different instance for {$name} than field/argument definitions. Make sure you always return the same instance for the same type name.");
            }
        }
    }

    /**
     * Validate the schema and return any errors.
     *
     * This operation requires a full schema scan. Do not use in production environment.
     *
     * @throws InvariantViolation
     *
     * @return array<int, Error>
     *
     * @api
     */
    public function validate(): array
    {
        // If this Schema has already been validated, return the previous results.
        if (isset($this->validationErrors)) {
            return $this->validationErrors;
        }

        // Validate the schema, producing a list of errors.
        $context = new SchemaValidationContext($this);
        $context->validateRootTypes();
        $context->validateDirectives();
        $context->validateTypes();

        // Persist the results of validation before returning to ensure validation
        // does not run multiple times for this schema.
        $this->validationErrors = $context->getErrors();

        return $this->validationErrors;
    }
}
